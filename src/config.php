<?php

return [
    'default'  => 'smtp', //smtp sendmail
    'from'     => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name'    => env('MAIL_FROM_NAME', 'Example'),
    ],
    'smtp'     => [
        'transport'  => 'smtp',
        'host'       => env('MAIL_HOST', 'mail.example.com'),
        'port'       => env('MAIL_PORT', 587),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'username'   => env('MAIL_USERNAME', 'username'),
        'password'   => env('MAIL_PASSWORD', 'password'),
        'timeout'    => null,
    ],
    'sendmail' => [
        'transport' => 'sendmail',
        'path'      => '/usr/sbin/sendmail -t -i',
    ],
    'log'      => [
        'transport' => 'log',
        'channel'   => env('MAIL_LOG_CHANNEL', 'file'),
    ],
];
