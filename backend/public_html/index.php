<?php

declare(strict_types=1);

use ChoreQuest\Controllers\ChoreListsController;
use ChoreQuest\Controllers\ChoresController;
use ChoreQuest\Controllers\NotificationsController;
use ChoreQuest\Controllers\UsersController;
use ChoreQuest\Database\Connection;
use ChoreQuest\Exceptions\HttpException;
use ChoreQuest\Http\Request;
use ChoreQuest\Http\Response;
use ChoreQuest\Routing\Router;
use ChoreQuest\Services\EmailService;

$config = require __DIR__ . '/../src/bootstrap.php';

$pdo = Connection::getInstance();
$emailService = new EmailService(
    $config['mail']['log_file'] ?? STORAGE_PATH . '/logs/password_reset.log',
    $config['mail']['reset_base_url'] ?? 'http://localhost:4200/reset-password?token='
);

$request = Request::fromGlobals();
$path = $request->path();

if (substr($path, 0, 4) !== '/api') {
    serveFrontend($path);
    return;
}

applyCors($config['app']['allowed_origins'] ?? ['*']);

if ($request->method() === 'OPTIONS') {
    Response::noContent()->send();
    return;
}

$router = new Router();
$usersController = new UsersController($pdo, $emailService);
$choreListsController = new ChoreListsController($pdo);
$choreController = new ChoresController($pdo);
$notificationsController = new NotificationsController($pdo);

$router->add('POST', '/api/users/register', fn (Request $req, array $params) => $usersController->register($req));
$router->add('POST', '/api/users/login', fn (Request $req, array $params) => $usersController->login($req));
$router->add('GET', '/api/users/{id}', fn (Request $req, array $params) => $usersController->show($req, $params));
$router->add('GET', '/api/users', fn (Request $req, array $params) => $usersController->index($req));
$router->add('POST', '/api/users/forgot-password', fn (Request $req, array $params) => $usersController->forgotPassword($req));
$router->add('POST', '/api/users/reset-password', fn (Request $req, array $params) => $usersController->resetPassword($req));

$router->add('GET', '/api/chorelists', fn (Request $req, array $params) => $choreListsController->index($req));
$router->add('GET', '/api/chorelists/{id}', fn (Request $req, array $params) => $choreListsController->show($req, $params));
$router->add('POST', '/api/chorelists', fn (Request $req, array $params) => $choreListsController->create($req));
$router->add('PUT', '/api/chorelists/{id}', fn (Request $req, array $params) => $choreListsController->update($req, $params));
$router->add('DELETE', '/api/chorelists/{id}', fn (Request $req, array $params) => $choreListsController->delete($req, $params));
$router->add('POST', '/api/chorelists/{id}/share', fn (Request $req, array $params) => $choreListsController->share($req, $params));
$router->add('DELETE', '/api/chorelists/{id}/share/{shareId}', fn (Request $req, array $params) => $choreListsController->removeShare($req, $params));

$router->add('GET', '/api/chorelists/{choreListId}/chores', fn (Request $req, array $params) => $choreController->index($req, $params));
$router->add('GET', '/api/chorelists/{choreListId}/chores/{id}', fn (Request $req, array $params) => $choreController->show($req, $params));
$router->add('POST', '/api/chorelists/{choreListId}/chores', fn (Request $req, array $params) => $choreController->create($req, $params));
$router->add('PUT', '/api/chorelists/{choreListId}/chores/{id}', fn (Request $req, array $params) => $choreController->update($req, $params));
$router->add('DELETE', '/api/chorelists/{choreListId}/chores/{id}', fn (Request $req, array $params) => $choreController->delete($req, $params));

$router->add('GET', '/api/notifications', fn (Request $req, array $params) => $notificationsController->index($req));
$router->add('PUT', '/api/notifications/{id}/read', fn (Request $req, array $params) => $notificationsController->markAsRead($req, $params));
$router->add('PUT', '/api/notifications/read-all', fn (Request $req, array $params) => $notificationsController->markAllAsRead($req));
$router->add('DELETE', '/api/notifications/{id}', fn (Request $req, array $params) => $notificationsController->delete($req, $params));

try {
    $response = $router->dispatch($request);
    $response->send();
} catch (HttpException $exception) {
    Response::json([
        'message' => $exception->getMessage(),
    ], $exception->getStatusCode())->send();
} catch (Throwable $throwable) {
    error_log($throwable->getMessage());
    Response::json([
        'message' => 'An unexpected error occurred.',
    ], 500)->send();
}

function applyCors(array $allowedOrigins): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($origin !== '' && (in_array('*', $allowedOrigins, true) || in_array($origin, $allowedOrigins, true))) {
        header('Access-Control-Allow-Origin: ' . ($origin === '' ? '*' : $origin));
    } elseif (in_array('*', $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: *');
    }

    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Credentials: true');
}

function serveFrontend(string $requestPath): void
{
    $frontendRoot = realpath(__DIR__ . '/app') ?: __DIR__ . '/app';

    $path = $requestPath === '/' ? '/index.html' : $requestPath;
    $candidate = $frontendRoot . $path;

    $resolved = realpath($candidate);

    if ($resolved === false || strpos($resolved, $frontendRoot) !== 0 || is_dir($resolved)) {
        $resolved = $frontendRoot . '/index.html';

        if (!is_file($resolved)) {
            http_response_code(404);
            echo 'Frontend bundle is not available.';
            return;
        }
    }

    $extension = strtolower((string)pathinfo($resolved, PATHINFO_EXTENSION));
    header('Content-Type: ' . mimeTypeFor($extension));

    if ($extension === 'html') {
        header('Cache-Control: no-cache');
    } else {
        header('Cache-Control: public, max-age=31536000, immutable');
    }

    readfile($resolved);
}

function mimeTypeFor(string $extension): string
{
    return match ($extension) {
        'html' => 'text/html; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'ico' => 'image/x-icon',
        'txt' => 'text/plain; charset=utf-8',
        'map' => 'application/json; charset=utf-8',
        default => 'application/octet-stream',
    };
}
