<?php

declare(strict_types=1);

namespace ChoreQuest\Database;

use PDO;
use PDOException;
use RuntimeException;

class Connection
{
    private static ?PDO $pdo = null;

    public static function init(array $config): void
    {
        if (self::$pdo !== null) {
            return;
        }

        $driver = strtolower((string)($config['driver'] ?? 'mysql'));

        try {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                self::$pdo = self::connectMysql($config);
                return;
            }

            if ($driver === 'sqlite') {
                self::$pdo = self::connectSqlite($config);
                return;
            }

            throw new RuntimeException('Unsupported database driver: ' . $driver);
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to establish database connection: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private static function connectMysql(array $config): PDO
    {
        $host = $config['host'] ?? 'localhost';
        $port = (int)($config['port'] ?? 3306);
        $database = $config['database'] ?? '';
    $charset = self::sanitizeIdentifier($config['charset'] ?? null, 'utf8mb4');
    $collation = self::sanitizeIdentifier($config['collation'] ?? null, 'utf8mb4_unicode_ci');
        $timezone = $config['timezone'] ?? '+00:00';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';

        if ($database === '') {
            throw new RuntimeException('Database name is required for MySQL connections.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $host,
            $port,
            $database,
            $charset
        );

        $options = $config['options'] ?? [];
        $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
        $options[PDO::ATTR_EMULATE_PREPARES] = false;

        $pdo = new PDO($dsn, $username, $password, $options);

        $pdo->exec(
            sprintf(
                "SET NAMES %s COLLATE %s",
                $pdo->quote($charset),
                $pdo->quote($collation)
            )
        );
        $pdo->exec('SET time_zone = ' . $pdo->quote($timezone));

        return $pdo;
    }

    private static function connectSqlite(array $config): PDO
    {
        $databasePath = $config['database'] ?? DATA_PATH . '/chorequest.db';
        $directory = dirname($databasePath);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('Unable to create database directory: ' . $directory);
            }
        }

        $pdo = new PDO('sqlite:' . $databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        if (!empty($config['foreign_keys'])) {
            $pdo->exec('PRAGMA foreign_keys = ON;');
        }

        return $pdo;
    }

    public static function getInstance(): PDO
    {
        if (self::$pdo === null) {
            throw new RuntimeException('Database connection has not been initialised.');
        }

        return self::$pdo;
    }

    private static function sanitizeIdentifier(mixed $value, string $fallback): string
    {
        if (!is_string($value)) {
            return $fallback;
        }

        return preg_match('/^[A-Za-z0-9_]+$/', $value) === 1 ? $value : $fallback;
    }
}
