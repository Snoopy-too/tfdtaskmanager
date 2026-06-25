<?php
declare(strict_types=1);

// Application Configuration Template
return [
    'db' => [
        'host' => '127.0.0.1',
        'dbname' => 'tfdtaskmgr',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'env' => 'development', // 'development' or 'production'
        'session_lifetime' => 1800, // 30 minutes in seconds
    ],
];
