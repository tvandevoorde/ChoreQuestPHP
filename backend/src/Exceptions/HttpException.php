<?php

declare(strict_types=1);

namespace ChoreQuest\Exceptions;

use RuntimeException;

class HttpException extends RuntimeException
{
    public function __construct(private readonly int $statusCode, string $message)
    {
        parent::__construct($message, 0);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
