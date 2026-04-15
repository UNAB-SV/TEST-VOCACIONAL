<?php

declare(strict_types=1);

return [
    'app_name' => 'Test Vocacional',
    'environment' => 'development',
    'debug' => true,
    'base_path' => dirname(__DIR__),
    'timezone' => 'UTC',
    'session' => [
        'enabled' => true,
        'name' => 'test_vocacional_session',
    ],
    'database' => [
        'enabled' => false,
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'test_vocacional',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
];
