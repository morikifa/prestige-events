<?php

return [
    'app' => [
        'name' => 'Prestige Events',
        'institute' => 'Колледж ИМСИТ, Краснодар',
        'timezone' => 'Europe/Moscow',
        'debug' => false,
        'base_url' => '',
    ],
    'db' => [
        'driver' => getenv('PRESTIGE_DB_DRIVER') ?: 'mysql',
        'host' => getenv('PRESTIGE_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('PRESTIGE_DB_PORT') ?: '3306',
        'name' => getenv('PRESTIGE_DB_NAME') ?: 'prestige_events',
        'user' => getenv('PRESTIGE_DB_USER') ?: 'root',
        'password' => getenv('PRESTIGE_DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
        'sqlite_path' => dirname(__DIR__) . '/storage/demo.sqlite',
    ],
    'security' => [
        'session_name' => 'prestige_session',
        'max_login_attempts' => 5,
        'lockout_minutes' => 10,
    ],
    'upload' => [
        'backup_dir' => dirname(__DIR__) . '/storage/backups',
    ],
];
