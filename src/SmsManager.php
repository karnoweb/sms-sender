<?php

namespace Karnoweb\SmsSender;

use Illuminate\Contracts\Container\Container;
use Karnoweb\SmsSender\Contracts\DeliveryReportFetcher;
use Karnoweb\SmsSender\Contracts\SmsDriver;
use Karnoweb\SmsSender\Contracts\SmsUsageHandler;
use Karnoweb\SmsSender\Enums\SmsSendStatusEnum;
use Karnoweb\SmsSender\Enums\SmsTemplateEnum;
use Karnoweb\SmsSender\Events\SmsFailed;
use Karnoweb\SmsSender\Events\SmsSent;
use Karnoweb\SmsSender\Events\SmsSending;
use Karnoweb\SmsSender\Exceptions\AllDriversFailedException;
use Karnoweb\SmsSender\Exceptions\DriverConnectionException;
use Karnoweb\SmsSender\Exceptions\DriverNotAvailableException;
use Karnoweb\SmsSender\Exceptions\DriverNotFoundException;
use Karnoweb\SmsSender\Exceptions\InvalidDriverConfigurationException;
use Karnoweb\SmsSender\Jobs\SendSmsJob;
use Karnoweb\SmsSender\Logging\SmsLogger;
use Karnoweb\SmsSender\Models\Sms;
use Karnoweb\SmsSender\Response\SmsResponse;
use Karnoweb\SmsSender\Retry\RetryHandler;
use Karnoweb\SmsSender\Support\NullUsageHandler;
use Karnoweb\SmsSender\Validation\SmsValidator;

/**
 * Main SMS manager — high-level Builder / Facade.
 */
class SmsManager
{
    /** @var array<int, string> */
    protected array $toNumbers = [];

    protected ?string $messageText = null;

    protected ?string $templateText = null;

    protected ?string $templateName = null;

    /** @var array<string, string> */
    protected array $inputs = [];

    protected ?string $from = null;

    protected ?string $currentDriver = null;

    protected SmsUsageHandler $usageHandler;

    protected SmsLogger $logger;

    public function __construct(
        protected readonly Container $container,
    ) {
        $this->usageHandler = $this->resolveUsageHandler();
        $this->logger       = new SmsLogger();
    }

    public function from(string $from): static
    {
        $this->from = $from;

        return $this;
    }

    public function driver(string $driver): static
    {
        $this->currentDriver = $driver;

        return $this;
    }

    public static function instance(): static
    {
        /** @var static $instance */
        $instance = app(static::class);

        return $instance;
    }

    // ═══════════════════════════════════════════════════════
    //  BUILDER
    // ═══════════════════════════════════════════════════════

    public function message(string $message): static
    {
        $this->messageText = $message;

        return $this;
    }

    /**
     * Set template by key and body (injected from application).
     * Use this instead of package lang so the app controls template content.
     */
    public function template(string $key, string $body): static
    {
        $this->templateName = $key;
        $this->templateText = $body;

        return $this;
    }

    /**
     * Set OTP template via enum (backward compatibility).
     * Prefer template($key, $body) with app-provided content.
     */
    public function otp(SmsTemplateEnum $template): static
    {
        $this->templateText = $template->templateText();
        $this->templateName = $template->name;

        return $this;
    }

    public function input(string $key, string $value): static
    {
        $this->inputs[$key] = $value;

        return $this;
    }

    public function inputs(array $inputs): static
    {
        $this->inputs = array_merge($this->inputs, $inputs);

        return $this;
    }

    public function number(string $phone): static
    {
        $this->toNumbers[] = $phone;

        return $this;
    }

    public function numbers(array $phones): static
    {
        foreach ($phones as $phone) {
            $this->toNumbers[] = (string) $phone;
        }

        return $this;
    }

    // ═══════════════════════════════════════════════════════
    //  SEND
    // ═══════════════════════════════════════════════════════

    public function send(): SmsResponse
    {
        try {
            $targets = $this->resolveTargets();
            $message = $this->resolveMessage();

            if ($message === null) {
                throw new InvalidDriverConfigurationException('No message or template provided.');
            }

            $validator = new SmsValidator();
            $validator->validate($targets, $message);

            if (config('sms.validation.normalize_numbers', true)) {
                $targets = $validator->normalizeNumbers($targets);
                $this->toNumbers = $targets;
            }

            return $this->sendToTargets($targets, $message);
        } finally {
            $this->reset();
        }
    }

    /**
     * Send via queue.
     */
    public function queue(?string $queueName = null): void
    {
        $targets = $this->resolveTargets();
        $message = $this->resolveMessage();

        if ($message === null) {
            throw new InvalidDriverConfigurationException('No message or template provided.');
        }

        $validator = new SmsValidator();
        $validator->validate($targets, $message);

        if (config('sms.validation.normalize_numbers', true)) {
            $targets = $validator->normalizeNumbers($targets);
        }

        $job = new SendSmsJob(
            recipients: $targets,
            message: $message,
            from: $this->from,
            driver: $this->currentDriver
        );

        if ($queueName !== null) {
            $job->onQueue($queueName);
        } elseif (config('sms.queue.name')) {
            $job->onQueue((string) config('sms.queue.name'));
        }

        dispatch($job);
        $this->reset();
    }

    /**
     * Send with delay.
     */
    public function later(int $delaySeconds, ?string $queueName = null): void
    {
        $targets = $this->resolveTargets();
        $message = $this->resolveMessage();

        if ($message === null) {
            throw new InvalidDriverConfigurationException('No message or template provided.');
        }

        $validator = new SmsValidator();
        $validator->validate($targets, $message);

        if (config('sms.validation.normalize_numbers', true)) {
            $targets = $validator->normalizeNumbers($targets);
        }

        $job = new SendSmsJob(
            recipients: $targets,
            message: $message,
            from: $this->from,
            driver: $this->currentDriver
        );

        if ($queueName !== null) {
            $job->onQueue($queueName);
        }

        dispatch($job)->delay(now()->addSeconds($delaySeconds));
        $this->reset();
    }

    // ═══════════════════════════════════════════════════════
    //  CHECK STATUS
    // ═══════════════════════════════════════════════════════

    /**
     * @return array<int, array<string, mixed>>
     */
    public function checkStatus(): array
    {
        try {
            $targets = $this->resolveTargets();

            if (empty($targets)) {
                throw new InvalidDriverConfigurationException(
                    'No recipients provided to check status.',
                );
            }

            return $this->fetchStatusForTargets($targets);
        } finally {
            $this->reset();
        }
    }

    /**
     * @param array<int, string> $phoneNumbers
     * @return array<int, array<string, mixed>>
     */
    protected function fetchStatusForTargets(array $phoneNumbers): array
    {
        /** @var class-string<Sms> $modelClass */
        $modelClass = config('sms.model', Sms::class);

        $results = [];

        foreach ($phoneNumbers as $phone) {
            $records = $modelClass::query()
                ->forPhone($phone)
                ->checkable()
                ->get();

            foreach ($records as $record) {
                $results[] = $this->checkSingleRecord($record);
            }
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    protected function checkSingleRecord(Sms $record): array
    {
        $baseResult = [
            'sms_id'              => $record->id,
            'phone'               => $record->phone,
            'driver'              => $record->driver,
            'provider_message_id' => $record->provider_message_id,
            'old_status'          => $record->status->value,
        ];

        if (! $record->hasProviderMessageId()) {
            return array_merge($baseResult, [
                'skipped' => true,
                'reason'  => 'No provider_message_id available.',
            ]);
        }

        try {
            $driver = $this->resolveDriver($record->driver);

            if (! $driver instanceof DeliveryReportFetcher) {
                return array_merge($baseResult, [
                    'skipped' => true,
                    'reason'  => 'Driver does not support delivery reports.',
                ]);
            }

            $report    = $driver->fetchDeliveryReport($record->provider_message_id);
            $newStatus = $report['status'] ?? 'unknown';

            $this->updateRecordStatus($record, $newStatus);

            return array_merge($baseResult, [
                'new_status' => $newStatus,
            ]);
        } catch (\Throwable $e) {
            return array_merge($baseResult, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function updateRecordStatus(Sms $record, string $newStatus): void
    {
        match ($newStatus) {
            'delivered' => $record->markAsDelivered(),
            'failed'    => $record->markAsFailed('Reported as failed by provider.'),
            default     => null,
        };
    }

    // ═══════════════════════════════════════════════════════
    //  RESOLVE
    // ═══════════════════════════════════════════════════════

    /** @return array<int, string> */
    protected function resolveTargets(): array
    {
        return array_values(array_unique($this->toNumbers));
    }

    protected function resolveMessage(): ?string
    {
        if ($this->messageText !== null) {
            return $this->messageText;
        }

        if ($this->templateText !== null) {
            return $this->compileTemplate($this->templateText, $this->inputs);
        }

        return null;
    }

    /**
     * @param array<string, string> $inputs
     */
    protected function compileTemplate(string $template, array $inputs = []): string
    {
        if (empty($inputs)) {
            return $template;
        }

        $search  = [];
        $replace = [];

        foreach ($inputs as $key => $value) {
            $search[]  = '{' . $key . '}';
            $replace[] = (string) $value;
        }

        return str_replace($search, $replace, $template);
    }

    // ═══════════════════════════════════════════════════════
    //  DRIVER
    // ═══════════════════════════════════════════════════════

    /** @return array<int, string> */
    protected function getDriverOrder(): array
    {
        $order = [];

        if ($this->currentDriver !== null && $this->currentDriver !== '') {
            $order[] = $this->currentDriver;
        }

        $default  = config('sms.default');
        $failover = config('sms.failover', []);

        if (! empty($default)) {
            $order[] = $default;
        }

        if (is_array($failover)) {
            $order = array_merge($order, $failover);
        }

        $order = array_values(array_unique($order));

        if (empty($order)) {
            throw new InvalidDriverConfigurationException(
                'No SMS driver configured. Set SMS_DRIVER in .env or update config/sms.php.',
            );
        }

        return $order;
    }

    protected function resolveDriver(string $name): SmsDriver
    {
        $driverConfig = config("sms.drivers.{$name}");

        if ($driverConfig === null || ! is_array($driverConfig) || empty($driverConfig)) {
            throw DriverNotFoundException::make($name);
        }

        $class = $driverConfig['class'] ?? null;

        if (empty($class) || ! is_string($class)) {
            throw new InvalidDriverConfigurationException(
                "Driver class for [{$name}] is not specified in config/sms.php.",
            );
        }

        if (! class_exists($class)) {
            throw new InvalidDriverConfigurationException(
                "Driver class [{$class}] for [{$name}] does not exist.",
            );
        }

        $credentials = $driverConfig['credentials'] ?? [];

        /** @var SmsDriver $driver */
        $driver = $this->container->make($class, [
            'config' => $credentials,
        ]);

        if (! $driver instanceof SmsDriver) {
            throw new InvalidDriverConfigurationException(
                "Driver [{$class}] must implement " . SmsDriver::class . '.',
            );
        }

        return $driver;
    }

    // ═══════════════════════════════════════════════════════
    //  SEND TO TARGETS
    // ═══════════════════════════════════════════════════════

    /**
     * @param array<int, string> $phoneNumbers
     */
    protected function sendToTargets(array $phoneNumbers, string $message): SmsResponse
    {
        $driverOrder  = $this->getDriverOrder();
        $retryHandler = new RetryHandler($this->logger);
        /** @var array<string, \Throwable> $errors */
        $errors = [];

        foreach ($driverOrder as $driverName) {
            $sendingEvent = new SmsSending($phoneNumbers, $message, $driverName);
            event($sendingEvent);

            if ($sendingEvent->cancelled) {
                continue;
            }

            try {
                $driver = $this->resolveDriver($driverName);
                $this->usageHandler->ensureUsable($driverName, $driver);

                $rawResponse = $retryHandler->execute($driverName, function () use ($driverName, $driver, $phoneNumbers, $message): array {
                    return $driver->send($phoneNumbers, $message, $this->from);
                });

                $messageId = $rawResponse['message_id'] ?? null;
                $this->saveRecordsAndMarkSent($driverName, $phoneNumbers, $message, $messageId);

                $this->logger->success($driverName, $phoneNumbers, $message);

                $response = SmsResponse::success(
                    driverName: $driverName,
                    recipients: $phoneNumbers,
                    messageId: $messageId,
                    rawResponse: $rawResponse
                );

                event(new SmsSent($response, $phoneNumbers, $message, $driverName));

                return $response;
            } catch (\Throwable $e) {
                $errors[$driverName] = $e;
                $this->logger->failure($driverName, $phoneNumbers, $e);
            }
        }

        $exception = new AllDriversFailedException($errors);
        event(new SmsFailed($phoneNumbers, $message, $exception, $errors));

        throw $exception;
    }

    /**
     * @param array<int, string> $phoneNumbers
     */
    protected function saveRecordsAndMarkSent(
        string $driverName,
        array $phoneNumbers,
        string $message,
        ?string $messageId,
    ): void {
        /** @var class-string<Sms> $modelClass */
        $modelClass = config('sms.model', Sms::class);

        foreach ($phoneNumbers as $phoneNumber) {
            /** @var Sms $record */
            $record = $modelClass::create([
                'driver'   => $driverName,
                'template' => $this->templateName,
                'inputs'   => ! empty($this->inputs) ? $this->inputs : null,
                'phone'    => $phoneNumber,
                'message'  => $message,
                'status'   => SmsSendStatusEnum::PENDING,
            ]);

            try {
                $record->markAsSent($messageId);
            } catch (\Throwable $e) {
                $record->markAsFailed($e->getMessage());
            }
        }
    }

    // ═══════════════════════════════════════════════════════
    //  USAGE HANDLER
    // ═══════════════════════════════════════════════════════

    protected function resolveUsageHandler(): SmsUsageHandler
    {
        $handlerClass = config('sms.usage_handler');

        if (! empty($handlerClass) && is_string($handlerClass)) {
            return $this->container->make($handlerClass);
        }

        if ($this->container->bound(SmsUsageHandler::class)) {
            return $this->container->make(SmsUsageHandler::class);
        }

        return new NullUsageHandler();
    }

    // ═══════════════════════════════════════════════════════
    //  RESET
    // ═══════════════════════════════════════════════════════

    protected function reset(): void
    {
        $this->toNumbers     = [];
        $this->messageText  = null;
        $this->templateText = null;
        $this->templateName = null;
        $this->inputs       = [];
        $this->from         = null;
        $this->currentDriver = null;
    }
}
