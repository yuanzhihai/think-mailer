<?php

return [
    'type'     => 'smtp', //smtp sendmail
    'from'     => [
        'address' => 'example@example',
        'name'    => 'App Name',
    ],
    'smtp'     => [
        'host'       => 'mail.example.com',
        'port'       => 587,
        'encryption' => 'tls',
        'username'   => 'username',
        'password'   => 'password',
        'timeout'    => null,
    ],
    'sendmail' => [
        'path' => '/usr/sbin/sendmail -t -i',
    ],
];
