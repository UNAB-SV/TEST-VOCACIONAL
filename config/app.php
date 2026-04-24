<?php

declare(strict_types=1);

return [
    'app_name' => 'Test Vocacional',
    'environment' => 'development',
    'debug' => true,
    'base_path' => dirname(__DIR__),
    'timezone' => 'America/El_Salvador',
    'session' => [
        'enabled' => true,
        'name' => 'test_vocacional_session',
    ],
    'database' => [
        'enabled' => true,
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'test_vocacional',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'catalog' => [
        // Opciones: mysql, json, mysql_with_json_fallback.
        //'source' => 'mysql_with_json_fallback',
	'source' => 'mysql',
        // current-json corresponde a la versión cargada por scripts/import_test_catalog.php.
        'version_key' => 'current-json',
    ],
    'catalog_ids' => [
        'el_salvador_country_id' => 15,
    ],
];
