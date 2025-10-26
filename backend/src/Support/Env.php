<?php

declare(strict_types=1);

namespace ChoreQuest\Support;

use RuntimeException;

class Env
{
    public static function load(string $filePath): void
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new RuntimeException('Unable to read environment file: ' . $filePath);
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            $delimiterPosition = strpos($line, '=');
            if ($delimiterPosition === false) {
                continue;
            }

            $name = trim(substr($line, 0, $delimiterPosition));
            $value = trim(substr($line, $delimiterPosition + 1));

            if ($value === '') {
                $parsedValue = '';
            } else {
                if ($value[0] === "'" && str_ends_with($value, "'")) {
                    $parsedValue = substr($value, 1, -1);
                } elseif ($value[0] === '"' && str_ends_with($value, '"')) {
                    $parsedValue = substr($value, 1, -1);
                } else {
                    $parsedValue = $value;
                }
            }

            $_ENV[$name] = $parsedValue;
            $_SERVER[$name] = $parsedValue;
            putenv($name . '=' . $parsedValue);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return $value === false ? $default : $value;
    }
}
