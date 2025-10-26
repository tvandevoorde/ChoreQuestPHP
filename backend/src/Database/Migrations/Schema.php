<?php

declare(strict_types=1);

namespace ChoreQuest\Database\Migrations;

use PDO;
use RuntimeException;

class Schema
{
    public static function migrate(PDO $pdo, array $config = []): void
    {
        $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        if ($driver === 'sqlite') {
            self::migrateSqlite($pdo);
            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            self::migrateMysql($pdo, $config);
            return;
        }

        throw new RuntimeException('Unsupported database driver: ' . $driver);
    }

    private static function migrateMysql(PDO $pdo, array $config): void
    {
        $charset = self::sanitizeIdentifier($config['charset'] ?? null, 'utf8mb4');
        $collation = self::sanitizeIdentifier($config['collation'] ?? null, 'utf8mb4_unicode_ci');
        $schema = self::requireIdentifier($config['database'] ?? null, 'Database name');
        $qualify = static fn(string $schemaName, string $table): string => sprintf('`%s`.`%s`', $schemaName, $table);

        $pdo->exec(sprintf(<<<SQL
            CREATE TABLE IF NOT EXISTS %s (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL UNIQUE,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=%s COLLATE=%s;
        SQL,
            $qualify($schema, 'users'),
            $charset,
            $collation
        ));

        $pdo->exec(sprintf(<<<SQL
            CREATE TABLE IF NOT EXISTS %s (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT NULL,
                owner_id INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_chore_lists_owner FOREIGN KEY(owner_id) REFERENCES %s(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=%s COLLATE=%s;
        SQL,
            $qualify($schema, 'chore_lists'),
            $qualify($schema, 'users'),
            $charset,
            $collation
        ));

        $pdo->exec(sprintf(<<<SQL
            CREATE TABLE IF NOT EXISTS %s (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                chore_list_id INT UNSIGNED NOT NULL,
                assigned_to_id INT UNSIGNED NULL,
                due_date DATETIME NULL,
                is_completed TINYINT(1) NOT NULL DEFAULT 0,
                completed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                is_recurring TINYINT(1) NOT NULL DEFAULT 0,
                recurrence_pattern VARCHAR(255) NULL,
                recurrence_interval INT NULL,
                recurrence_end_date DATETIME NULL,
                CONSTRAINT fk_chores_chore_list FOREIGN KEY(chore_list_id) REFERENCES %s(id) ON DELETE CASCADE,
                CONSTRAINT fk_chores_assigned_to FOREIGN KEY(assigned_to_id) REFERENCES %s(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=%s COLLATE=%s;
        SQL,
            $qualify($schema, 'chores'),
            $qualify($schema, 'chore_lists'),
            $qualify($schema, 'users'),
            $charset,
            $collation
        ));

        $pdo->exec(sprintf(<<<SQL
            CREATE TABLE IF NOT EXISTS %s (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chore_list_id INT UNSIGNED NOT NULL,
                shared_with_user_id INT UNSIGNED NOT NULL,
                permission VARCHAR(50) NOT NULL DEFAULT 'View',
                shared_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_chore_list_shares (chore_list_id, shared_with_user_id),
                CONSTRAINT fk_chore_list_shares_list FOREIGN KEY(chore_list_id) REFERENCES %s(id) ON DELETE CASCADE,
                CONSTRAINT fk_chore_list_shares_user FOREIGN KEY(shared_with_user_id) REFERENCES %s(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=%s COLLATE=%s;
        SQL,
            $qualify($schema, 'chore_list_shares'),
            $qualify($schema, 'chore_lists'),
            $qualify($schema, 'users'),
            $charset,
            $collation
        ));

        $pdo->exec(sprintf(<<<SQL
            CREATE TABLE IF NOT EXISTS %s (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                type VARCHAR(50) NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                related_chore_id INT UNSIGNED NULL,
                CONSTRAINT fk_notifications_user FOREIGN KEY(user_id) REFERENCES %s(id) ON DELETE CASCADE,
                CONSTRAINT fk_notifications_chore FOREIGN KEY(related_chore_id) REFERENCES %s(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=%s COLLATE=%s;
        SQL,
            $qualify($schema, 'notifications'),
            $qualify($schema, 'users'),
            $qualify($schema, 'chores'),
            $charset,
            $collation
        ));

        $pdo->exec(sprintf(<<<SQL
            CREATE TABLE IF NOT EXISTS %s (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                token VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                is_used TINYINT(1) NOT NULL DEFAULT 0,
                CONSTRAINT fk_reset_tokens_user FOREIGN KEY(user_id) REFERENCES %s(id) ON DELETE CASCADE,
                UNIQUE KEY uq_reset_tokens_token (token)
            ) ENGINE=InnoDB DEFAULT CHARSET=%s COLLATE=%s;
        SQL,
            $qualify($schema, 'password_reset_tokens'),
            $qualify($schema, 'users'),
            $charset,
            $collation
        ));
    }

    private static function migrateSqlite(PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL
            );
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS chore_lists (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT DEFAULT '',
                owner_id INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY(owner_id) REFERENCES users(id) ON DELETE CASCADE
            );
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS chores (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT DEFAULT '',
                chore_list_id INTEGER NOT NULL,
                assigned_to_id INTEGER,
                due_date TEXT,
                is_completed INTEGER NOT NULL DEFAULT 0,
                completed_at TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                is_recurring INTEGER NOT NULL DEFAULT 0,
                recurrence_pattern TEXT,
                recurrence_interval INTEGER,
                recurrence_end_date TEXT,
                FOREIGN KEY(chore_list_id) REFERENCES chore_lists(id) ON DELETE CASCADE,
                FOREIGN KEY(assigned_to_id) REFERENCES users(id) ON DELETE SET NULL
            );
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS chore_list_shares (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                chore_list_id INTEGER NOT NULL,
                shared_with_user_id INTEGER NOT NULL,
                permission TEXT NOT NULL DEFAULT 'View',
                shared_at TEXT NOT NULL,
                UNIQUE(chore_list_id, shared_with_user_id),
                FOREIGN KEY(chore_list_id) REFERENCES chore_lists(id) ON DELETE CASCADE,
                FOREIGN KEY(shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE
            );
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                message TEXT NOT NULL,
                type TEXT NOT NULL,
                is_read INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                related_chore_id INTEGER,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY(related_chore_id) REFERENCES chores(id) ON DELETE SET NULL
            );
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token TEXT NOT NULL,
                created_at TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                is_used INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE(token)
            );
        SQL);
    }

    private static function sanitizeIdentifier(?string $value, string $fallback): string
    {
        if (!is_string($value)) {
            return $fallback;
        }

        return preg_match('/^[A-Za-z0-9_]+$/', $value) === 1 ? $value : $fallback;
    }

    private static function requireIdentifier(?string $value, string $context): string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z0-9_]+$/', $value) !== 1) {
            throw new RuntimeException($context . ' must contain only letters, numbers, or underscores.');
        }

        return $value;
    }
}
