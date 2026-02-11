<?php

return [
    'send_failed' => '[{driver}] ارسال پیام ناموفق: {message}',
    'driver_not_found' => 'درایور «{driver}» یافت نشد. درایورهای موجود: {available}',
    'all_drivers_failed' => "تمام درایورها ناموفق بودند:\n:summary",
    'invalid_phone_format' => 'شماره موبایل «{number}» فرمت معتبری ندارد. فرمت صحیح: 09xxxxxxxxx یا +989xxxxxxxxx',
    'invalid_phone_empty' => 'شماره موبایل نمی‌تواند خالی باشد.',
    'invalid_message_empty' => 'متن پیام نمی‌تواند خالی باشد.',
    'invalid_message_too_long' => 'متن پیام ({length} کاراکتر) از حداکثر مجاز ({max} کاراکتر) بیشتر است.',
    'log_success' => 'SMS ارسال شد',
    'log_failure' => 'SMS ارسال نشد',
    'log_retry' => 'SMS retry تلاش {attempt}/{max}',
    'assert_sent' => 'پیامی به شماره {recipient} ارسال نشده است.',
    'assert_not_sent' => 'پیامی به شماره {recipient} ارسال شده در حالی که نباید.',
    'assert_nothing_sent' => 'پیام(هایی) ارسال شده در حالی که نباید.',
    'assert_sent_count' => 'تعداد پیام‌های ارسال‌شده ({actual}) با مقدار مورد انتظار ({expected}) مطابقت ندارد.',
    'assert_queued' => 'پیامی به شماره {recipient} در صف قرار نگرفته.',
    'assert_nothing_queued' => 'پیام(هایی) در صف قرار گرفته در حالی که نباید.',
];
