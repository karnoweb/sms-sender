<?php

namespace Karnoweb\SmsSender;

use Illuminate\Contracts\Container\Container;
use Karnoweb\SmsSender\Contracts\DeliveryReportFetcher;
use Karnoweb\SmsSender\Contracts\SmsDriver;
use Karnoweb\SmsSender\Contracts\SmsUsageHandler;
use Karnoweb\SmsSender\Enums\SmsSendStatusEnum;
use Karnoweb\SmsSender\Enums\SmsTemplateEnum;
use Karnoweb\SmsSender\Exceptions\DriverConnectionException;
use Karnoweb\SmsSender\Exceptions\DriverNotAvailableException;
use Karnoweb\SmsSender\Exceptions\InvalidDriverConfigurationException;
use Karnoweb\SmsSender\Models\Sms;
use Karnoweb\SmsSender\Support\NullUsageHandler;

/**
 * مدیر اصلی ارسال پیامک — Builder / Facade سطح بالا.
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

    protected SmsUsageHandler $usageHandler;

    public function __construct(
        protected readonly Container $container,
    ) {
        $this->usageHandler = $this->resolveUsageHandler();
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

    public function otp(SmsTemplateEnum $template): static
    {
        $this->templateText = $template->value;
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

    public function send(): void
    {
        try {
            $targets = $this->resolveTargets();

            if (empty($targets)) {
                throw new InvalidDriverConfigurationException('No recipients provided.');
            }

            $message = $this->resolveMessage();

            if ($message === null) {
                throw new InvalidDriverConfigurationException('No message or template provided.');
            }

            $this->sendToTargets($targets, $message);
        } finally {
            $this->reset();
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

    // ═══════════════════════════════════════════════════════
    //  DRIVER
    // ═══════════════════════════════════════════════════════

    /** @return array<int, string> */
    protected function getDriverOrder(): array
    {
        $default  = config('sms.default');
        $failover = config('sms.failover', []);

        $order = [];

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

        if (! is_array($driverConfig) || empty($driverConfig)) {
            throw new InvalidDriverConfigurationException(
                "SMS driver [{$name}] is not defined in config/sms.php.",
            );
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
    protected function sendToTargets(array $phoneNumbers, string $message): void
    {
        $driverOrder   = $this->getDriverOrder();
        $lastException = null;

        foreach ($driverOrder as $driverName) {
            try {
                $driver = $this->resolveDriver($driverName);
                $this->usageHandler->ensureUsable($driverName, $driver);
                $this->sendWithDriver($driverName, $driver, $phoneNumbers, $message);

                return;
            } catch (InvalidDriverConfigurationException $e) {
                $lastException = $e;
                continue;
            } catch (DriverConnectionException $e) {
                $lastException = $e;
                continue;
            } catch (DriverNotAvailableException $e) {
                $lastException = $e;
                continue;
            }
        }

        throw new DriverNotAvailableException(
            message:  'No SMS drivers are available to send messages.',
            previous: $lastException,
        );
    }

    /**
     * @param array<int, string> $phoneNumbers
     */
    protected function sendWithDriver(
        string $driverName,
        SmsDriver $driver,
        array $phoneNumbers,
        string $message,
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
                $driver->send($phoneNumber, $message);
                $record->markAsSent();
            } catch (DriverConnectionException $e) {
                $record->markAsFailed($e->getMessage());
                throw $e;
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
        $this->toNumbers    = [];
        $this->messageText  = null;
        $this->templateText = null;
        $this->templateName = null;
        $this->inputs       = [];
    }
}
