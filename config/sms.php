<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Driver
    |--------------------------------------------------------------------------
    |
    | The default driver used for sending SMS. This value must match one of
    | the keys in the 'drivers' array below. Use 'null' in development
    | to avoid sending real SMS.
    |
    */

    'default' => env('SMS_DRIVER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Failover Drivers
    |--------------------------------------------------------------------------
    |
    | Ordered list of fallback drivers. If the default driver fails due to
    | connection or configuration error, each driver in this list is tried
    | in order until one succeeds.
    |
    | Example: ['kavenegar', 'melipayamak']
    |
    */

    'failover' => [],

    /*
    |--------------------------------------------------------------------------
    | Template Injection
    |--------------------------------------------------------------------------
    |
    | Optional template bodies keyed by name. Used by SmsTemplateEnum when
    | resolving template text. Prefer injecting templates from your app via
    | Sms::template($key, $body) or override this in your published config.
    |
    */

    'templates' => [],

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    'validation' => [
        'max_message_length' => 500,
        'normalize_numbers'  => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'enabled' => env('SMS_LOGGING_ENABLED', true),
        'channel' => env('SMS_LOG_CHANNEL', null),
        'level'   => [
            'success' => 'info',
            'failure' => 'error',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry
    |--------------------------------------------------------------------------
    */

    'retry' => [
        'enabled'    => env('SMS_RETRY_ENABLED', true),
        'attempts'   => 3,
        'delay'      => 1000,
        'multiplier' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'enabled'     => env('SMS_QUEUE_ENABLED', false),
        'name'        => env('SMS_QUEUE_NAME', 'sms'),
        'connection'  => env('SMS_QUEUE_CONNECTION', null),
        'tries'       => 3,
        'retry_delay' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Drivers
    |--------------------------------------------------------------------------
    |
    | Each driver is defined by its class and credentials. The driver class
    | must implement SmsDriver and may optionally implement DeliveryReportFetcher.
    |
    | A driver may have a 'usage' key for quota/rate limiting. If 'usage' is
    | not set, the driver has no usage limits.
    |
    | Usage structure:
    |   'enabled'       => bool     Enable/disable (default: true)
    |   'daily_limit'   => int|null Max sends per day (null = no limit)
    |   'monthly_limit' => int|null Max sends per month (null = no limit)
    |
    */

    'drivers' => [

        'null' => [
            'class'       => \Karnoweb\SmsSender\Drivers\NullDriver::class,
            'credentials' => [],
        ],

        'kavenegar' => [
            'class'       => \Karnoweb\SmsSender\Drivers\KavenegarDriver::class,
            'credentials' => [
                'token'  => env('KAVENEGAR_API_TOKEN'),
                'sender' => env('KAVENEGAR_SENDER'),
            ],
        ],

        'melipayamak' => [
            'class'       => \Karnoweb\SmsSender\Drivers\MelliPayamakDriver::class,
            'credentials' => [
                'username' => env('MELIPAYAMAK_USERNAME'),
                'password' => env('MELIPAYAMAK_PASSWORD'),
                'sender'   => env('MELIPAYAMAK_SENDER'),
            ],
        ],

        'smsir' => [
            'class'       => \Karnoweb\SmsSender\Drivers\SmsIrDriver::class,
            'credentials' => [
                'api_key' => env('SMSIR_API_KEY'),
                'sender'  => env('SMSIR_SENDER'),
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Log Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to store SMS logs. You may replace this with
    | your own model provided the table structure is compatible.
    |
    */

    'model' => \Karnoweb\SmsSender\Models\Sms::class,

    /*
    |--------------------------------------------------------------------------
    | SMS Table Name
    |--------------------------------------------------------------------------
    |
    | The database table name for SMS logs. The default package model uses
    | this value.
    |
    */

    'table' => 'sms_messages',

    /*
    |--------------------------------------------------------------------------
    | Usage Handler
    |--------------------------------------------------------------------------
    |
    | Class responsible for driver usage control (quota, rate-limit, enable/disable).
    | Set to null for no limits (NullUsageHandler is used).
    |
    */

    'usage_handler' => null,

];
