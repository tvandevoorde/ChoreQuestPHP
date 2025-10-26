<?php

declare(strict_types=1);

namespace ChoreQuest\Controllers;

use ChoreQuest\Exceptions\HttpException;
use ChoreQuest\Http\Request;
use ChoreQuest\Http\Response;
use PDO;

class NotificationsController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function index(Request $request): Response
    {
        $userId = $request->query('userId');
        if (!is_numeric($userId)) {
            throw new HttpException(400, 'Query parameter userId is required.');
        }

        $statement = $this->pdo->prepare(
            'SELECT * FROM notifications WHERE user_id = :user ORDER BY created_at DESC LIMIT 50'
        );
        $statement->execute(['user' => (int)$userId]);
        $notifications = $statement->fetchAll();

        $mapped = array_map(fn (array $row): array => $this->mapNotification($row), $notifications);

        return Response::json($mapped);
    }

    public function markAsRead(Request $request, array $params): Response
    {
        $id = $this->mustIntParam($params, 'id');

        $statement = $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id');
        $statement->execute(['id' => $id]);

        if ($statement->rowCount() === 0) {
            throw new HttpException(404, 'Notification not found');
        }

        return Response::noContent();
    }

    public function markAllAsRead(Request $request): Response
    {
        $userId = $request->query('userId');
        if (!is_numeric($userId)) {
            throw new HttpException(400, 'Query parameter userId is required.');
        }

        $statement = $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :user AND is_read = 0');
        $statement->execute(['user' => (int)$userId]);

        return Response::noContent();
    }

    public function delete(Request $request, array $params): Response
    {
        $id = $this->mustIntParam($params, 'id');

        $statement = $this->pdo->prepare('DELETE FROM notifications WHERE id = :id');
        $statement->execute(['id' => $id]);

        if ($statement->rowCount() === 0) {
            throw new HttpException(404, 'Notification not found');
        }

        return Response::noContent();
    }

    private function mapNotification(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'title' => (string)$row['title'],
            'message' => (string)$row['message'],
            'type' => (string)$row['type'],
            'isRead' => ((int)$row['is_read']) === 1,
            'createdAt' => (string)$row['created_at'],
            'relatedChoreId' => $row['related_chore_id'] === null ? null : (int)$row['related_chore_id'],
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
}
