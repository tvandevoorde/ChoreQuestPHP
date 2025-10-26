<?php

declare(strict_types=1);

namespace ChoreQuest\Controllers;

use ChoreQuest\Exceptions\HttpException;
use ChoreQuest\Http\Request;
use ChoreQuest\Http\Response;
use PDO;

class ChoresController
{
    private const ALLOWED_RECURRENCE = ['daily', 'weekly', 'monthly', 'yearly'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function index(Request $request, array $params): Response
    {
        $listId = $this->mustIntParam($params, 'choreListId');
        $this->ensureChoreListExists($listId);

        $statement = $this->pdo->prepare(
            'SELECT c.*, u.username AS assigned_username
             FROM chores c
             LEFT JOIN users u ON u.id = c.assigned_to_id
             WHERE c.chore_list_id = :list
             ORDER BY c.id'
        );
        $statement->execute(['list' => $listId]);
        $chores = $statement->fetchAll();

        return Response::json(array_map(fn (array $row): array => $this->mapChore($row), $chores));
    }

    public function show(Request $request, array $params): Response
    {
        $listId = $this->mustIntParam($params, 'choreListId');
        $choreId = $this->mustIntParam($params, 'id');

        $chore = $this->findChore($listId, $choreId);

        return Response::json($this->mapChore($chore));
    }

    public function create(Request $request, array $params): Response
    {
        $listId = $this->mustIntParam($params, 'choreListId');
        $choreList = $this->findChoreList($listId);

        $payload = $request->json();
        $title = trim((string)($payload['title'] ?? ''));
        $description = trim((string)($payload['description'] ?? ''));

        if ($title === '') {
            throw new HttpException(400, 'Title is required.');
        }

        $assignedToId = $this->optionalInt($payload['assignedToId'] ?? null);
        if ($assignedToId !== null && !$this->userExists($assignedToId)) {
            throw new HttpException(400, 'Assigned user not found');
        }

        $dueDate = $this->parseDate($payload['dueDate'] ?? null);
        $isRecurring = $this->boolValue($payload['isRecurring'] ?? false) ?? false;

        $recurrencePattern = null;
        $recurrenceInterval = null;
        $recurrenceEndDate = null;

        if ($isRecurring) {
            $pattern = strtolower((string)($payload['recurrencePattern'] ?? ''));
            if (!in_array($pattern, self::ALLOWED_RECURRENCE, true)) {
                throw new HttpException(400, 'Invalid recurrence pattern.');
            }
            $recurrencePattern = ucfirst($pattern);

            $intervalRaw = $payload['recurrenceInterval'] ?? null;
            if (!is_numeric($intervalRaw) || (int)$intervalRaw < 1) {
                throw new HttpException(400, 'recurrenceInterval must be >= 1.');
            }
            $recurrenceInterval = (int)$intervalRaw;

            $recurrenceEndDate = $this->parseDate($payload['recurrenceEndDate'] ?? null);
        }

        $now = gmdate('c');
        $statement = $this->pdo->prepare(
            'INSERT INTO chores (
                title,
                description,
                chore_list_id,
                assigned_to_id,
                due_date,
                is_completed,
                completed_at,
                created_at,
                updated_at,
                is_recurring,
                recurrence_pattern,
                recurrence_interval,
                recurrence_end_date
            ) VALUES (
                :title,
                :description,
                :list_id,
                :assigned_to,
                :due_date,
                0,
                NULL,
                :created_at,
                :updated_at,
                :is_recurring,
                :recurrence_pattern,
                :recurrence_interval,
                :recurrence_end_date
            )'
        );
        $statement->execute([
            'title' => $title,
            'description' => $description,
            'list_id' => $listId,
            'assigned_to' => $assignedToId,
            'due_date' => $dueDate,
            'created_at' => $now,
            'updated_at' => $now,
            'is_recurring' => $isRecurring ? 1 : 0,
            'recurrence_pattern' => $recurrencePattern,
            'recurrence_interval' => $recurrenceInterval,
            'recurrence_end_date' => $recurrenceEndDate,
        ]);

        $choreId = (int)$this->pdo->lastInsertId();

        if ($assignedToId !== null) {
            $this->createNotification(
                $assignedToId,
                'Chore Assigned',
                "You have been assigned to '{$title}'",
                'ChoreAssigned',
                $choreId
            );
        }

        $chore = $this->findChore($listId, $choreId);

        return Response::json($this->mapChore($chore), 201, [
            'Location' => '/api/chorelists/' . $listId . '/chores/' . $choreId,
        ]);
    }

    public function update(Request $request, array $params): Response
    {
        $listId = $this->mustIntParam($params, 'choreListId');
        $choreId = $this->mustIntParam($params, 'id');

        $original = $this->findChore($listId, $choreId);
        $updated = $original;
        $payload = $request->json();
        $previousAssignedToId = $original['assigned_to_id'];

        if (array_key_exists('title', $payload)) {
            $title = trim((string)$payload['title']);
            if ($title === '') {
                throw new HttpException(400, 'Title cannot be empty.');
            }
            $updated['title'] = $title;
        }

        if (array_key_exists('description', $payload)) {
            $updated['description'] = trim((string)$payload['description']);
        }

        if (array_key_exists('assignedToId', $payload)) {
            $assignedToId = $this->optionalInt($payload['assignedToId']);
            if ($assignedToId !== null && !$this->userExists($assignedToId)) {
                throw new HttpException(400, 'Assigned user not found');
            }
            $updated['assigned_to_id'] = $assignedToId;
        }

        if (array_key_exists('dueDate', $payload)) {
            $updated['due_date'] = $this->parseDate($payload['dueDate']);
        }

        if (array_key_exists('isRecurring', $payload)) {
            $isRecurring = $this->boolValue($payload['isRecurring']);
            if ($isRecurring === null) {
                throw new HttpException(400, 'Invalid value for isRecurring.');
            }
            $updated['is_recurring'] = $isRecurring ? 1 : 0;
        }

        if (array_key_exists('recurrencePattern', $payload)) {
            $pattern = strtolower((string)$payload['recurrencePattern']);
            if ($pattern !== '' && !in_array($pattern, self::ALLOWED_RECURRENCE, true)) {
                throw new HttpException(400, 'Invalid recurrence pattern.');
            }
            $updated['recurrence_pattern'] = $pattern === '' ? null : ucfirst($pattern);
        }

        if (array_key_exists('recurrenceInterval', $payload)) {
            if ($payload['recurrenceInterval'] === null) {
                $updated['recurrence_interval'] = null;
            } elseif (!is_numeric($payload['recurrenceInterval']) || (int)$payload['recurrenceInterval'] < 1) {
                throw new HttpException(400, 'recurrenceInterval must be >= 1.');
            } else {
                $updated['recurrence_interval'] = (int)$payload['recurrenceInterval'];
            }
        }

        if (array_key_exists('recurrenceEndDate', $payload)) {
            if ($payload['recurrenceEndDate'] === null || $payload['recurrenceEndDate'] === '') {
                $updated['recurrence_end_date'] = null;
            } else {
                $updated['recurrence_end_date'] = $this->parseDate($payload['recurrenceEndDate']);
            }
        }

        if (array_key_exists('isCompleted', $payload)) {
            $isCompleted = $this->boolValue($payload['isCompleted']);
            if ($isCompleted === null) {
                throw new HttpException(400, 'Invalid value for isCompleted.');
            }

            if ($isCompleted) {
                $updated['is_completed'] = 1;
                $updated['completed_at'] = gmdate('c');

                if (
                    (($updated['is_recurring'] ?? $original['is_recurring']) == 1) &&
                    ($updated['due_date'] ?? $original['due_date']) !== null &&
                    ($updated['recurrence_pattern'] ?? $original['recurrence_pattern']) !== null
                ) {
                    $interval = $updated['recurrence_interval'] ?? $original['recurrence_interval'] ?? 1;
                    $nextDueDate = $this->calculateNextDueDate(
                        (string)($updated['due_date'] ?? $original['due_date']),
                        (string)($updated['recurrence_pattern'] ?? $original['recurrence_pattern']),
                        (int)$interval
                    );

                    if ($nextDueDate !== null) {
                        $endDate = $updated['recurrence_end_date'] ?? $original['recurrence_end_date'];
                        if ($endDate === null || $nextDueDate <= new \DateTimeImmutable((string)$endDate)) {
                            $updated['due_date'] = $nextDueDate->format('c');
                            $updated['is_completed'] = 0;
                            $updated['completed_at'] = null;
                        }
                    }
                }
            } else {
                $updated['is_completed'] = 0;
                $updated['completed_at'] = null;
            }
        }

        $updated['updated_at'] = gmdate('c');

        $columns = [
            'title',
            'description',
            'assigned_to_id',
            'due_date',
            'is_completed',
            'completed_at',
            'updated_at',
            'is_recurring',
            'recurrence_pattern',
            'recurrence_interval',
            'recurrence_end_date',
        ];

        $setParts = [];
        $parameters = ['id' => $choreId, 'list' => $listId];
        foreach ($columns as $column) {
            $newValue = $updated[$column] ?? null;
            $oldValue = $original[$column] ?? null;

            if ($newValue === $oldValue) {
                continue;
            }

            if ($newValue === null) {
                $setParts[] = $column . ' = NULL';
            } else {
                $setParts[] = $column . ' = :' . $column;
                $parameters[$column] = $newValue;
            }
        }

        if ($setParts === []) {
            return Response::json($this->mapChore($original));
        }

        $sql = 'UPDATE chores SET ' . implode(', ', $setParts) . ' WHERE id = :id AND chore_list_id = :list';
        $this->pdo->prepare($sql)->execute($parameters);

        $newAssignedToId = $updated['assigned_to_id'];
        if ($newAssignedToId !== null && $newAssignedToId != $previousAssignedToId) {
            $this->createNotification(
                (int)$newAssignedToId,
                'Chore Assigned',
                "You have been assigned to '{$updated['title']}'",
                'ChoreAssigned',
                $choreId
            );
        }

        $reloaded = $this->findChore($listId, $choreId);

        return Response::json($this->mapChore($reloaded));
    }

    public function delete(Request $request, array $params): Response
    {
        $listId = $this->mustIntParam($params, 'choreListId');
        $choreId = $this->mustIntParam($params, 'id');

        $statement = $this->pdo->prepare('DELETE FROM chores WHERE id = :id AND chore_list_id = :list');
        $statement->execute(['id' => $choreId, 'list' => $listId]);

        if ($statement->rowCount() === 0) {
            throw new HttpException(404, 'Chore not found');
        }

        return Response::noContent();
    }

    private function calculateNextDueDate(string $currentDueDate, string $pattern, int $interval): ?\DateTimeImmutable
    {
        try {
            $date = new \DateTimeImmutable($currentDueDate);
        } catch (\Exception) {
            return null;
        }

        return match (strtolower($pattern)) {
            'daily' => $date->modify('+' . $interval . ' days'),
            'weekly' => $date->modify('+' . $interval . ' weeks'),
            'monthly' => $date->modify('+' . $interval . ' months'),
            'yearly' => $date->modify('+' . $interval . ' years'),
            default => null,
        };
    }

    private function createNotification(int $userId, string $title, string $message, string $type, int $relatedChoreId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO notifications (user_id, title, message, type, is_read, created_at, related_chore_id)
             VALUES (:user_id, :title, :message, :type, 0, :created_at, :related_chore_id)'
        );
        $statement->execute([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'created_at' => gmdate('c'),
            'related_chore_id' => $relatedChoreId,
        ]);
    }

    private function mapChore(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'title' => (string)$row['title'],
            'description' => (string)$row['description'],
            'choreListId' => (int)$row['chore_list_id'],
            'assignedToId' => $row['assigned_to_id'] === null ? null : (int)$row['assigned_to_id'],
            'assignedToUsername' => $row['assigned_username'] ?? null,
            'dueDate' => $row['due_date'],
            'isCompleted' => ((int)$row['is_completed']) === 1,
            'completedAt' => $row['completed_at'],
            'createdAt' => (string)$row['created_at'],
            'updatedAt' => (string)$row['updated_at'],
            'isRecurring' => ((int)($row['is_recurring'] ?? 0)) === 1,
            'recurrencePattern' => $row['recurrence_pattern'],
            'recurrenceInterval' => $row['recurrence_interval'] === null ? null : (int)$row['recurrence_interval'],
            'recurrenceEndDate' => $row['recurrence_end_date'],
        ];
    }

    private function optionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new HttpException(400, 'Value must be numeric.');
        }

        return (int)$value;
    }

    private function boolValue(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lower = strtolower($value);
            if (in_array($lower, ['true', '1', 'yes'], true)) {
                return true;
            }
            if (in_array($lower, ['false', '0', 'no'], true)) {
                return false;
            }
        }

        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        return null;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = new \DateTimeImmutable((string)$value);
        } catch (\Exception) {
            throw new HttpException(400, 'Invalid date format.');
        }

        return $date->format('c');
    }

    private function findChore(int $listId, int $choreId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.*, u.username AS assigned_username
             FROM chores c
             LEFT JOIN users u ON u.id = c.assigned_to_id
             WHERE c.id = :id AND c.chore_list_id = :list
             LIMIT 1'
        );
        $statement->execute(['id' => $choreId, 'list' => $listId]);
        $row = $statement->fetch();

        if (!$row) {
            throw new HttpException(404, 'Chore not found');
        }

        return $row;
    }

    private function findChoreList(int $listId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM chore_lists WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $listId]);
        $row = $statement->fetch();

        if (!$row) {
            throw new HttpException(404, 'Chore list not found');
        }

        return $row;
    }

    private function ensureChoreListExists(int $listId): void
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM chore_lists WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $listId]);

        if (!$statement->fetchColumn()) {
            throw new HttpException(404, 'Chore list not found');
        }
    }

    private function userExists(int $userId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $userId]);

        return (bool)$statement->fetchColumn();
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
