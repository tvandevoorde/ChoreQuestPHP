<?php

declare(strict_types=1);

namespace ChoreQuest\Services;

use RuntimeException;

class EmailService
{
    public function __construct(
        private readonly string $logFile,
        private readonly string $resetBaseUrl,
    ) {
        $directory = dirname($this->logFile);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('Unable to create log directory: ' . $directory);
            }
        }
    }

    public function sendPasswordResetEmail(string $toEmail, string $username, string $token): void
    {
        $resetUrl = $this->resetBaseUrl . $token;
        $entry = sprintf(
            "[%s] Password reset requested for %s <%s>\nReset link: %s\n\n",
            gmdate('c'),
            $username,
            $toEmail,
            $resetUrl,
        );

        if (file_put_contents($this->logFile, $entry, FILE_APPEND) === false) {
            throw new RuntimeException('Unable to write to log file: ' . $this->logFile);
        }
    }
}
