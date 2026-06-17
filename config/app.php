<?php

return [
    'app' => [
        'name'    => $_ENV['APP_NAME'] ?? 'EFD REINF',
        'url'     => $_ENV['APP_URL'] ?? 'http://localhost',
        'env'     => $_ENV['APP_ENV'] ?? 'production',
        'secret'  => $_ENV['APP_SECRET'] ?? 'change_me',
    ],
    'db' => [
        'host'   => $_ENV['DB_HOST'] ?? 'db',
        'port'   => $_ENV['DB_PORT'] ?? '3306',
        'name'   => $_ENV['DB_NAME'] ?? 'efd_reinf',
        'user'   => $_ENV['DB_USER'] ?? 'reinf_user',
        'pass'   => $_ENV['DB_PASS'] ?? 'reinf_pass123',
        'charset'=> 'utf8mb4',
    ],
    'upload' => [
        'path'     => __DIR__ . '/../public/uploads/',
        'max_size' => 50 * 1024 * 1024, // 50MB
        'allowed'  => ['xlsx', 'xls'],
    ],
];
