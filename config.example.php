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
    'lobby' => [
        'publish_url' => 'http://127.0.0.1:4002', // Production internal/public lobby origin
        'publish_secret' => 'replace-with-shared-hmac-secret', // Must match SANDBOX_PUBLISH_SECRET in lobby .env
        'public_url' => 'https://lobby.theflyingdutchmen.games',
        'timeout' => 30,
        'verify_tls' => true,
    ],
];
