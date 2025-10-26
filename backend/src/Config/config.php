<?php

declare(strict_types=1);

$allowedOrigins = \ChoreQuest\Support\Env::get('APP_ALLOWED_ORIGINS');

if (is_string($allowedOrigins)) {
    $allowedOrigins = array_values(array_filter(array_map('trim', explode(',', $allowedOrigins))));
}

if (empty($allowedOrigins)) {
    $allowedOrigins = ['http://localhost:4200'];
}

return [
    'app' => [
        'env' => \ChoreQuest\Support\Env::get('APP_ENV', 'development'),
        'allowed_origins' => $allowedOrigins,
    ],
    'database' => [
        'driver' => \ChoreQuest\Support\Env::get('DB_DRIVER', 'mysql'),
        'host' => \ChoreQuest\Support\Env::get('DB_HOST', 'localhost'),
        'port' => (int)\ChoreQuest\Support\Env::get('DB_PORT', 3306),
        'database' => \ChoreQuest\Support\Env::get('DB_DATABASE', 'chorequest'),
        'username' => \ChoreQuest\Support\Env::get('DB_USERNAME', 'chorequest'),
        'password' => \ChoreQuest\Support\Env::get('DB_PASSWORD', 'chorequest'),
        'charset' => \ChoreQuest\Support\Env::get('DB_CHARSET', 'utf8mb4'),
        'collation' => \ChoreQuest\Support\Env::get('DB_COLLATION', 'utf8mb4_unicode_ci'),
        'timezone' => \ChoreQuest\Support\Env::get('DB_TIMEZONE', '+00:00'),
        'options' => [],
    ],
    'mail' => [
        'reset_base_url' => \ChoreQuest\Support\Env::get('MAIL_RESET_BASE_URL', 'http://localhost:4200/reset-password?token='),
        'log_file' => STORAGE_PATH . '/logs/password_reset.log',
    ],
];
