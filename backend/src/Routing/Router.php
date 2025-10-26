<?php

declare(strict_types=1);

namespace ChoreQuest\Routing;

use ChoreQuest\Exceptions\HttpException;
use ChoreQuest\Http\Request;
use ChoreQuest\Http\Response;

class Router
{
    /** @var array<int, array{method: string, regex: string, handler: callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $normalised = rtrim($pattern, '/');
        if ($normalised === '') {
            $normalised = '/';
        }

        $regex = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $normalised);
        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            if (!preg_match($route['regex'], $request->path(), $matches)) {
                continue;
            }

            $parameters = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $parameters[$key] = $value;
                }
            }

            $response = ($route['handler'])($request, $parameters);

            if (!$response instanceof Response) {
                throw new HttpException(500, 'Route handler did not return a valid response instance.');
            }

            return $response;
        }

        throw new HttpException(404, 'Resource not found.');
    }
}
