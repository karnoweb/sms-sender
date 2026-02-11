<?php

return [
    'send_failed' => '[{driver}] Send failed: {message}',
    'driver_not_found' => 'Driver "{driver}" not found. Available: {available}',
    'all_drivers_failed' => "All drivers failed:\n:summary",
    'invalid_phone_format' => 'Invalid phone format: {number}. Expected: 09xxxxxxxxx or +989xxxxxxxxx',
    'invalid_phone_empty' => 'Phone number cannot be empty.',
    'invalid_message_empty' => 'Message text cannot be empty.',
    'invalid_message_too_long' => 'Message length ({length} chars) exceeds maximum ({max} chars).',
    'log_success' => 'SMS sent',
    'log_failure' => 'SMS send failed',
    'log_retry' => 'SMS retry attempt {attempt}/{max}',
    'assert_sent' => 'No message was sent to {recipient}.',
    'assert_not_sent' => 'A message was sent to {recipient} but should not have been.',
    'assert_nothing_sent' => 'Messages were sent but should not have been.',
    'assert_sent_count' => 'Sent count ({actual}) does not match expected ({expected}).',
    'assert_queued' => 'No message was queued for {recipient}.',
    'assert_nothing_queued' => 'Messages were queued but should not have been.',
];
