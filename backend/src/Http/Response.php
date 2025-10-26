<?php

declare(strict_types=1);

namespace ChoreQuest\Http;

use JsonException;

class Response
{
    public function __construct(
        private readonly mixed $body,
        private readonly int $status = 200,
        private readonly array $headers = [],
    ) {
    }

    public static function json(mixed $body, int $status = 200, array $headers = []): self
    {
        return new self($body, $status, $headers);
    }

    public static function noContent(): self
    {
        return new self(null, 204);
    }

    public function send(): void
    {
        http_response_code($this->status);

        $hasContent = $this->body !== null && $this->status !== 204;
        if ($hasContent) {
            header('Content-Type: application/json');
        }

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        if (!$hasContent) {
            return;
        }

        try {
            echo json_encode($this->body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            http_response_code(500);
            echo json_encode([
                'message' => 'Failed to encode response payload.',
            ]);
        }
    }
}
