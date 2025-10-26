<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

const BASE_PATH = __DIR__ . '/..';
const STORAGE_PATH = BASE_PATH . '/storage';
const DATA_PATH = BASE_PATH . '/data';

spl_autoload_register(function (string $class): void {
    $prefix = 'ChoreQuest\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

$envPath = BASE_PATH . '/.env';

\ChoreQuest\Support\Env::load($envPath);

$config = require __DIR__ . '/Config/config.php';

\ChoreQuest\Database\Connection::init($config['database'] ?? []);
\ChoreQuest\Database\Migrations\Schema::migrate(
    \ChoreQuest\Database\Connection::getInstance(),
    $config['database'] ?? []
);

return $config;
