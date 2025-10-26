<?php

declare(strict_types=1);

namespace ChoreQuest\Controllers;

use ChoreQuest\Exceptions\HttpException;
use ChoreQuest\Http\Request;
use ChoreQuest\Http\Response;
use ChoreQuest\Services\EmailService;
use PDO;
use Throwable;

class UsersController
{
    public function __construct(private readonly PDO $pdo, private readonly EmailService $emailService)
    {
    }

    public function register(Request $request): Response
    {
        $payload = $request->json();
        $username = trim((string)($payload['username'] ?? ''));
        $email = trim((string)($payload['email'] ?? ''));
        $password = (string)($payload['password'] ?? '');

        if ($username === '' || $email === '' || $password === '') {
            throw new HttpException(400, 'Username, email, and password are required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(400, 'Email address is invalid.');
        }

        if ($this->exists('SELECT 1 FROM users WHERE username = :value', ['value' => $username])) {
            throw new HttpException(400, 'Username already exists');
        }

        if ($this->exists('SELECT 1 FROM users WHERE email = :value', ['value' => $email])) {
            throw new HttpException(400, 'Email already exists');
        }

        $now = gmdate('c');
        $insert = $this->pdo->prepare(
            'INSERT INTO users (username, email, password_hash, created_at) VALUES (:username, :email, :hash, :created_at)'
        );
        $insert->execute([
            'username' => $username,
            'email' => $email,
            'hash' => password_hash($password, PASSWORD_BCRYPT),
            'created_at' => $now,
        ]);

        $id = (int)$this->pdo->lastInsertId();
        $user = $this->getUserById($id);

        return Response::json($this->mapUser($user), 201, [
            'Location' => '/api/users/' . $id,
        ]);
    }

    public function login(Request $request): Response
    {
        $payload = $request->json();
        $username = trim((string)($payload['username'] ?? ''));
        $password = (string)($payload['password'] ?? '');

        if ($username === '' || $password === '') {
            throw new HttpException(400, 'Username and password are required.');
        }

        $statement = $this->pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            throw new HttpException(401, 'Invalid credentials');
        }

        return Response::json($this->mapUser($user));
    }

    public function show(Request $request, array $params): Response
    {
        $id = $this->mustIntParam($params, 'id');
        $user = $this->getUserById($id);

        return Response::json($this->mapUser($user));
    }

    public function index(Request $request): Response
    {
        $statement = $this->pdo->query('SELECT id, username, email, created_at FROM users ORDER BY id ASC');
        $users = $statement->fetchAll();

        $mapped = array_map(fn (array $user): array => $this->mapUser($user), $users);

        return Response::json($mapped);
    }

    public function forgotPassword(Request $request): Response
    {
        $payload = $request->json();
        $email = trim((string)($payload['email'] ?? ''));

        if ($email === '') {
            throw new HttpException(400, 'Email is required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(400, 'Email address is invalid.');
        }

        $statement = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if ($user) {
            $token = $this->generateResetToken();
            $now = gmdate('c');
            $expiresAt = gmdate('c', time() + 3600);

            $insert = $this->pdo->prepare(
                'INSERT INTO password_reset_tokens (user_id, token, created_at, expires_at, is_used) VALUES (:user_id, :token, :created_at, :expires_at, 0)'
            );
            $insert->execute([
                'user_id' => (int)$user['id'],
                'token' => $token,
                'created_at' => $now,
                'expires_at' => $expiresAt,
            ]);

            try {
                $this->emailService->sendPasswordResetEmail((string)$user['email'], (string)$user['username'], $token);
            } catch (Throwable $exception) {
                // Log failures silently to avoid leaking account existence while still surfacing server issues elsewhere.
                error_log('Failed to log password reset email: ' . $exception->getMessage());
            }
        }

        return Response::json([
            'message' => 'If the email exists, a password reset link has been sent.',
        ]);
    }

    public function resetPassword(Request $request): Response
    {
        $payload = $request->json();
        $token = trim((string)($payload['token'] ?? '')); 
        $newPassword = (string)($payload['newPassword'] ?? '');

        if ($token === '' || $newPassword === '') {
            throw new HttpException(400, 'Token and newPassword are required.');
        }

        $statement = $this->pdo->prepare(
            'SELECT prt.*, u.id AS user_id FROM password_reset_tokens prt JOIN users u ON u.id = prt.user_id WHERE prt.token = :token AND prt.is_used = 0 LIMIT 1'
        );
        $statement->execute(['token' => $token]);
        $resetToken = $statement->fetch();

        if (!$resetToken) {
            throw new HttpException(400, 'Invalid or expired reset token');
        }

        $expiresAt = new \DateTimeImmutable((string)$resetToken['expires_at']);
        if ($expiresAt <= new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
            throw new HttpException(400, 'Invalid or expired reset token');
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $updateUser = $this->pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $updateUser->execute([
            'hash' => $hash,
            'id' => (int)$resetToken['user_id'],
        ]);

        $markToken = $this->pdo->prepare('UPDATE password_reset_tokens SET is_used = 1 WHERE id = :id');
        $markToken->execute(['id' => (int)$resetToken['id']]);

        return Response::json([
            'message' => 'Password has been reset successfully',
        ]);
    }

    private function exists(string $sql, array $params): bool
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (bool)$statement->fetchColumn();
    }

    /** @return array<string, mixed> */
    private function getUserById(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT id, username, email, created_at FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();

        if (!$user) {
            throw new HttpException(404, 'User not found');
        }

        return $user;
    }

    /** @param array<string, mixed> $user */
    private function mapUser(array $user): array
    {
        return [
            'id' => (int)$user['id'],
            'username' => (string)$user['username'],
            'email' => (string)$user['email'],
            'createdAt' => (string)$user['created_at'],
        ];
    }

    /** @param array<string, mixed> $params */
    private function mustIntParam(array $params, string $key): int
    {
        if (!isset($params[$key]) || !is_numeric($params[$key])) {
            throw new HttpException(400, 'Missing required route parameter: ' . $key);
        }

        return (int)$params[$key];
    }

    private function generateResetToken(): string
    {
        return bin2hex(random_bytes(40));
    }
}
