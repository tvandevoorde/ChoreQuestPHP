<?php

declare(strict_types=1);

namespace ChoreQuest\Http;

use ChoreQuest\Support\Str;
use ChoreQuest\Exceptions\HttpException;

class Request
{
    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $headers,
        private readonly array $body,
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = Str::normalisePath($path);

        $headers = [];
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $key => $value) {
                $headers[strtolower($key)] = $value;
            }
        }

        $rawBody = file_get_contents('php://input') ?: '';
        $decodedBody = [];

        if ($rawBody !== '') {
            $decoded = json_decode($rawBody, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                throw new HttpException(400, 'Invalid JSON payload.');
            }

            $decodedBody = $decoded;
        }

        return new self($method, $path, $_GET ?? [], $headers, $decodedBody);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function allQuery(): array
    {
        return $this->query;
    }

    public function json(): array
    {
        return $this->body;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        $key = strtolower($key);
        return $this->headers[$key] ?? $default;
    }
}
