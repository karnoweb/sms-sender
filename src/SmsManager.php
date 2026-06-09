<?php

namespace Karnoweb\SmsSender;

use Illuminate\Contracts\Container\Container;
use Karnoweb\SmsSender\Contracts\DeliveryReportFetcher;
use Karnoweb\SmsSender\Contracts\LookupCapable;
use Karnoweb\SmsSender\Contracts\SmsDriver;
use Karnoweb\SmsSender\Contracts\SmsUsageHandler;
use Karnoweb\SmsSender\Enums\SmsSendStatusEnum;
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

    protected ?string $providerTemplate = null;

    protected bool $isOtpMode = false;

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
        if ($this->isOtpMode) {
            throw new InvalidDriverConfigurationException(
                'Cannot combine message() with otp()/lookup().',
            );
        }

        $this->messageText = $message;

        return $this;
    }

    /**
     * Set template by key and body (injected from application).
     * Use this instead of package lang so the app controls template content.
     */
    public function template(string $key, string $body): static
    {
        if ($this->isOtpMode) {
            throw new InvalidDriverConfigurationException(
                'Cannot combine template() with otp()/lookup().',
            );
        }

        $this->templateName = $key;
        $this->templateText = $body;

        return $this;
    }

    /**
     * Send OTP via provider template (Kavenegar verify/lookup, etc.).
     * $template is the provider template name (e.g. "login"), not local display text.
     */
    public function otp(string $template): static
    {
        $this->assertNotMixedWithPlainMessage();
        $this->providerTemplate = $template;
        $this->isOtpMode        = true;

        return $this;
    }

    /**
     * Alias for otp() — same provider-template lookup flow.
     */
    public function lookup(string $template): static
    {
        return $this->otp($template);
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
        if ($this->isOtpMode && ! empty($this->toNumbers)) {
            throw new InvalidDriverConfigurationException(
                'OTP/lookup supports exactly one recipient. Use number() once.',
            );
        }

        $this->toNumbers[] = $phone;

        return $this;
    }

    public function numbers(array $phones): static
    {
        if ($this->isOtpMode) {
            throw new InvalidDriverConfigurationException(
                'OTP/lookup does not support numbers(). Use number() with a single recipient.',
            );
        }

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
            if ($this->isOtpMode) {
                return $this->sendOtp();
            }

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
        if ($this->isOtpMode) {
            $this->queueOtp($queueName);

            return;
        }

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
            driver: $this->currentDriver,
        );

        $this->dispatchJob($job, $queueName);
        $this->reset();
    }

    /**
     * Send with delay.
     */
    public function later(int $delaySeconds, ?string $queueName = null): void
    {
        if ($this->isOtpMode) {
            $this->queueOtp($queueName, $delaySeconds);

            return;
        }

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
            driver: $this->currentDriver,
        );

        $this->dispatchJob($job, $queueName, $delaySeconds);
        $this->reset();
    }

    protected function queueOtp(?string $queueName = null, ?int $delaySeconds = null): void
    {
        $targets = $this->resolveTargets();

        if ($this->providerTemplate === null || $this->providerTemplate === '') {
            throw new InvalidDriverConfigurationException('No provider template provided for OTP/lookup.');
        }

        if (count($targets) !== 1) {
            throw new InvalidDriverConfigurationException(
                'OTP/lookup supports exactly one recipient.',
            );
        }

        $validator = new SmsValidator();
        $validator->validateRecipients($targets);

        if (config('sms.validation.normalize_numbers', true)) {
            $targets = $validator->normalizeNumbers($targets);
        }

        $job = new SendSmsJob(
            recipients: $targets,
            from: $this->from,
            driver: $this->currentDriver,
            isOtpMode: true,
            providerTemplate: $this->providerTemplate,
            inputs: $this->inputs,
        );

        $this->dispatchJob($job, $queueName, $delaySeconds);
        $this->reset();
    }

    protected function dispatchJob(SendSmsJob $job, ?string $queueName = null, ?int $delaySeconds = null): void
    {
        if ($queueName !== null) {
            $job->onQueue($queueName);
        } elseif (config('sms.queue.name')) {
            $job->onQueue((string) config('sms.queue.name'));
        }

        if ($delaySeconds !== null) {
            dispatch($job)->delay(now()->addSeconds($delaySeconds));
        } else {
            dispatch($job);
        }
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

    protected function assertNotMixedWithPlainMessage(): void
    {
        if ($this->messageText !== null || $this->templateText !== null) {
            throw new InvalidDriverConfigurationException(
                'Cannot combine otp()/lookup() with message() or template().',
            );
        }
    }

    protected function compileDisplayTemplate(string $providerTemplate, array $inputs): string
    {
        $templateText = $this->resolveDisplayTemplateText($providerTemplate);

        if ($templateText === null || $templateText === '') {
            return $providerTemplate;
        }

        return $this->compileTemplate($templateText, $inputs);
    }

    protected function resolveDisplayTemplateText(string $providerTemplate): ?string
    {
        $lookups = config('sms.lookups', []);
        if (is_array($lookups)) {
            foreach ($lookups as $key => $name) {
                if ((string) $name === $providerTemplate) {
                    $fromConfig = config('sms.templates.' . $key);
                    if (is_string($fromConfig) && $fromConfig !== '') {
                        return $fromConfig;
                    }
                }
            }
        }

        $direct = config('sms.templates.' . $providerTemplate);
        if (is_string($direct) && $direct !== '') {
            return $direct;
        }

        return null;
    }

    protected function resolveProviderMessageFromRaw(?array $rawResponse): ?string
    {
        $message = $rawResponse['raw']['entries'][0]['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : null;
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
    //  SEND OTP / LOOKUP
    // ═══════════════════════════════════════════════════════

    protected function sendOtp(): SmsResponse
    {
        $targets = $this->resolveTargets();

        if ($this->providerTemplate === null || $this->providerTemplate === '') {
            throw new InvalidDriverConfigurationException('No provider template provided for OTP/lookup.');
        }

        if (count($targets) !== 1) {
            throw new InvalidDriverConfigurationException(
                'OTP/lookup supports exactly one recipient.',
            );
        }

        $validator = new SmsValidator();
        $validator->validateRecipients($targets);

        if (config('sms.validation.normalize_numbers', true)) {
            $targets = $validator->normalizeNumbers($targets);
            $this->toNumbers = $targets;
        }

        return $this->sendOtpToTarget($targets[0]);
    }

    protected function sendOtpToTarget(string $phoneNumber): SmsResponse
    {
        $driverOrder  = $this->getDriverOrder();
        $retryHandler = new RetryHandler($this->logger);
        $displayMessage = $this->compileDisplayTemplate($this->providerTemplate, $this->inputs);
        /** @var array<string, \Throwable> $errors */
        $errors = [];

        foreach ($driverOrder as $driverName) {
            $sendingEvent = new SmsSending([$phoneNumber], $displayMessage, $driverName);
            event($sendingEvent);

            if ($sendingEvent->cancelled) {
                continue;
            }

            try {
                $driver = $this->resolveDriver($driverName);

                if (! $driver instanceof LookupCapable) {
                    throw new DriverNotAvailableException(
                        "Driver [{$driverName}] does not support OTP/lookup.",
                    );
                }

                $this->usageHandler->ensureUsable($driverName, $driver);

                $rawResponse = $retryHandler->execute($driverName, function () use ($driver, $phoneNumber): array {
                    return $driver->lookup(
                        $phoneNumber,
                        $this->providerTemplate,
                        $this->inputs,
                    );
                });

                $providerMessage = $this->resolveProviderMessageFromRaw($rawResponse);
                $logMessage      = $providerMessage ?? $displayMessage;
                $messageId       = $rawResponse['message_id'] ?? null;

                $this->saveOtpRecordAndMarkSent(
                    $driverName,
                    $phoneNumber,
                    $logMessage,
                    $messageId,
                );

                $this->logger->success($driverName, [$phoneNumber], $logMessage);

                $response = SmsResponse::success(
                    driverName: $driverName,
                    recipients: [$phoneNumber],
                    messageId: $messageId,
                    rawResponse: $rawResponse,
                );

                event(new SmsSent($response, [$phoneNumber], $logMessage, $driverName));

                return $response;
            } catch (\Throwable $e) {
                $errors[$driverName] = $e;
                $this->logger->failure($driverName, [$phoneNumber], $e);
            }
        }

        $exception = new AllDriversFailedException($errors);
        event(new SmsFailed([$phoneNumber], $displayMessage, $exception, $errors));

        throw $exception;
    }

    protected function saveOtpRecordAndMarkSent(
        string $driverName,
        string $phoneNumber,
        string $displayMessage,
        ?string $messageId,
    ): void {
        /** @var class-string<Sms> $modelClass */
        $modelClass = config('sms.model', Sms::class);

        /** @var Sms $record */
        $record = $modelClass::create([
            'driver'   => $driverName,
            'template' => $this->providerTemplate,
            'inputs'   => ! empty($this->inputs) ? $this->inputs : null,
            'phone'    => $phoneNumber,
            'message'  => $displayMessage,
            'status'   => SmsSendStatusEnum::PENDING,
        ]);

        try {
            $record->markAsSent($messageId);
        } catch (\Throwable $e) {
            $record->markAsFailed($e->getMessage());
        }
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
        $this->toNumbers         = [];
        $this->messageText       = null;
        $this->templateText      = null;
        $this->templateName      = null;
        $this->inputs            = [];
        $this->providerTemplate  = null;
        $this->isOtpMode         = false;
        $this->from              = null;
        $this->currentDriver     = null;
    }
}
