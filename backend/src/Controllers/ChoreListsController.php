<?php

declare(strict_types=1);

namespace ChoreQuest\Controllers;

use ChoreQuest\Exceptions\HttpException;
use ChoreQuest\Http\Request;
use ChoreQuest\Http\Response;
use PDO;

class ChoreListsController
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

        $userId = (int)$userId;

        $ownedStatement = $this->pdo->prepare(
            'SELECT cl.*, u.username AS owner_username
             FROM chore_lists cl
             JOIN users u ON u.id = cl.owner_id
             WHERE cl.owner_id = :user_id'
        );
        $ownedStatement->execute(['user_id' => $userId]);
        $owned = $ownedStatement->fetchAll();

        $sharedStatement = $this->pdo->prepare(
            'SELECT DISTINCT cl.*, u.username AS owner_username
             FROM chore_list_shares cls
             JOIN chore_lists cl ON cl.id = cls.chore_list_id
             JOIN users u ON u.id = cl.owner_id
             WHERE cls.shared_with_user_id = :user_id'
        );
        $sharedStatement->execute(['user_id' => $userId]);
        $shared = $sharedStatement->fetchAll();

        $merged = $this->mergeChoreLists($owned, $shared);
        $response = $this->hydrateChoreLists($merged);

        return Response::json($response);
    }

    public function show(Request $request, array $params): Response
    {
        $id = $this->mustIntParam($params, 'id');
        $choreList = $this->findChoreList($id);

        return Response::json($this->hydrateChoreLists([$choreList])[0]);
    }

    public function create(Request $request): Response
    {
        $userId = $request->query('userId');
        if (!is_numeric($userId)) {
            throw new HttpException(400, 'Query parameter userId is required.');
        }

        $userId = (int)$userId;
        if (!$this->userExists($userId)) {
            throw new HttpException(400, 'User not found');
        }

        $payload = $request->json();
        $name = trim((string)($payload['name'] ?? ''));
        $description = trim((string)($payload['description'] ?? ''));

        if ($name === '') {
            throw new HttpException(400, 'Name is required.');
        }

        $now = gmdate('c');
        $insert = $this->pdo->prepare(
            'INSERT INTO chore_lists (name, description, owner_id, created_at, updated_at)
             VALUES (:name, :description, :owner_id, :created_at, :updated_at)'
        );
        $insert->execute([
            'name' => $name,
            'description' => $description,
            'owner_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = (int)$this->pdo->lastInsertId();
        $choreList = $this->findChoreList($id);

        return Response::json($this->hydrateChoreLists([$choreList])[0], 201, [
            'Location' => '/api/chorelists/' . $id,
        ]);
    }

    public function update(Request $request, array $params): Response
    {
        $id = $this->mustIntParam($params, 'id');
        $payload = $request->json();

        $fields = [];
        if (array_key_exists('name', $payload)) {
            $fields['name'] = trim((string)$payload['name']);
        }
        if (array_key_exists('description', $payload)) {
            $fields['description'] = trim((string)$payload['description']);
        }

        if (empty($fields)) {
            $fields['updated_at'] = gmdate('c');
            $this->pdo->prepare('UPDATE chore_lists SET updated_at = :updated_at WHERE id = :id')
                ->execute(['updated_at' => $fields['updated_at'], 'id' => $id]);
        } else {
            $setParts = [];
            $parameters = ['id' => $id];

            foreach ($fields as $column => $value) {
                if ($column === 'name' && $value === '') {
                    throw new HttpException(400, 'Name cannot be empty.');
                }

                $setParts[] = $column . ' = :' . $column;
                $parameters[$column] = $value;
            }

            $setParts[] = 'updated_at = :updated_at';
            $parameters['updated_at'] = gmdate('c');

            $sql = 'UPDATE chore_lists SET ' . implode(', ', $setParts) . ' WHERE id = :id';
            $statement = $this->pdo->prepare($sql);

            if ($statement->execute($parameters) === false || $statement->rowCount() === 0) {
                throw new HttpException(404, 'Chore list not found');
            }
        }

        $choreList = $this->findChoreList($id);
        return Response::json($this->hydrateChoreLists([$choreList])[0]);
    }

    public function delete(Request $request, array $params): Response
    {
        $id = $this->mustIntParam($params, 'id');
        $statement = $this->pdo->prepare('DELETE FROM chore_lists WHERE id = :id');
        $statement->execute(['id' => $id]);

        if ($statement->rowCount() === 0) {
            throw new HttpException(404, 'Chore list not found');
        }

        return Response::noContent();
    }

    public function share(Request $request, array $params): Response
    {
    $id = $this->mustIntParam($params, 'id');
    $choreList = $this->findChoreList($id);

        $payload = $request->json();
        $sharedWithUserId = $payload['sharedWithUserId'] ?? null;
        $permission = strtoupper(trim((string)($payload['permission'] ?? 'View')));

        if (!is_numeric($sharedWithUserId)) {
            throw new HttpException(400, 'sharedWithUserId is required.');
        }

        $sharedWithUserId = (int)$sharedWithUserId;

        if (!$this->userExists($sharedWithUserId)) {
            throw new HttpException(400, 'User not found');
        }

        $allowedPermissions = ['VIEW', 'EDIT', 'ADMIN'];
        if (!in_array($permission, $allowedPermissions, true)) {
            $permission = 'VIEW';
        }

        if ($this->exists(
            'SELECT 1 FROM chore_list_shares WHERE chore_list_id = :list AND shared_with_user_id = :user',
            ['list' => $id, 'user' => $sharedWithUserId]
        )) {
            throw new HttpException(400, 'List already shared with this user');
        }

        $now = gmdate('c');
        $statement = $this->pdo->prepare(
            'INSERT INTO chore_list_shares (chore_list_id, shared_with_user_id, permission, shared_at)
             VALUES (:list_id, :user_id, :permission, :shared_at)'
        );
        $statement->execute([
            'list_id' => $id,
            'user_id' => $sharedWithUserId,
            'permission' => ucfirst(strtolower($permission)),
            'shared_at' => $now,
        ]);

        $shareId = (int)$this->pdo->lastInsertId();

        $notification = $this->pdo->prepare(
            'INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
             VALUES (:user_id, :title, :message, :type, 0, :created_at)'
        );
        $notification->execute([
            'user_id' => $sharedWithUserId,
            'title' => 'Chore List Shared',
            'message' => "A chore list '{$choreList['name']}' has been shared with you",
            'type' => 'ListShared',
            'created_at' => $now,
        ]);

        $share = $this->fetchShare($shareId);

        return Response::json($share);
    }

    public function removeShare(Request $request, array $params): Response
    {
        $listId = $this->mustIntParam($params, 'id');
        $shareId = $this->mustIntParam($params, 'shareId');

        $statement = $this->pdo->prepare(
            'DELETE FROM chore_list_shares WHERE id = :share_id AND chore_list_id = :list_id'
        );
        $statement->execute([
            'share_id' => $shareId,
            'list_id' => $listId,
        ]);

        if ($statement->rowCount() === 0) {
            throw new HttpException(404, 'Share not found');
        }

        return Response::noContent();
    }

    /**
     * @param array<int, array<string, mixed>> $primary
     * @param array<int, array<string, mixed>> $secondary
     * @return array<int, array<string, mixed>>
     */
    private function mergeChoreLists(array $primary, array $secondary): array
    {
        $merged = [];

        foreach ([$primary, $secondary] as $collection) {
            foreach ($collection as $row) {
                $merged[$row['id']] = $row;
            }
        }

        return array_values($merged);
    }

    /** @param array<int, array<string, mixed>> $lists */
    private function hydrateChoreLists(array $lists): array
    {
        if ($lists === []) {
            return [];
        }

        $ids = array_map(fn (array $list): int => (int)$list['id'], $lists);
        $counts = $this->fetchChoreCounts($ids);
        $shares = $this->fetchShares($ids);

        return array_map(
            function (array $list) use ($counts, $shares): array {
                $id = (int)$list['id'];

                return [
                    'id' => $id,
                    'name' => (string)$list['name'],
                    'description' => (string)$list['description'],
                    'ownerId' => (int)$list['owner_id'],
                    'ownerUsername' => (string)$list['owner_username'],
                    'createdAt' => (string)$list['created_at'],
                    'updatedAt' => (string)$list['updated_at'],
                    'choreCount' => $counts[$id]['total'] ?? 0,
                    'completedChoreCount' => $counts[$id]['completed'] ?? 0,
                    'shares' => $shares[$id] ?? [],
                ];
            },
            $lists,
        );
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array{total: int, completed: int}>
     */
    private function fetchChoreCounts(array $ids): array
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare(
            'SELECT chore_list_id, COUNT(*) AS total, SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) AS completed
             FROM chores
             WHERE chore_list_id IN (' . $placeholders . ')
             GROUP BY chore_list_id'
        );
        $statement->execute($ids);
        $rows = $statement->fetchAll();

        $results = [];
        foreach ($rows as $row) {
            $results[(int)$row['chore_list_id']] = [
                'total' => (int)$row['total'],
                'completed' => (int)($row['completed'] ?? 0),
            ];
        }

        return $results;
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function fetchShares(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare(
            'SELECT cls.*, u.username AS shared_username
             FROM chore_list_shares cls
             JOIN users u ON u.id = cls.shared_with_user_id
             WHERE cls.chore_list_id IN (' . $placeholders . ')
             ORDER BY cls.shared_at DESC'
        );
        $statement->execute($ids);
        $rows = $statement->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int)$row['chore_list_id']][] = [
                'id' => (int)$row['id'],
                'sharedWithUserId' => (int)$row['shared_with_user_id'],
                'sharedWithUsername' => (string)$row['shared_username'],
                'permission' => (string)$row['permission'],
                'sharedAt' => (string)$row['shared_at'],
            ];
        }

        return $grouped;
    }

    private function fetchShare(int $shareId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT cls.*, u.username AS shared_username
             FROM chore_list_shares cls
             JOIN users u ON u.id = cls.shared_with_user_id
             WHERE cls.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $shareId]);
        $share = $statement->fetch();

        if (!$share) {
            throw new HttpException(404, 'Share not found');
        }

        return [
            'id' => (int)$share['id'],
            'sharedWithUserId' => (int)$share['shared_with_user_id'],
            'sharedWithUsername' => (string)$share['shared_username'],
            'permission' => (string)$share['permission'],
            'sharedAt' => (string)$share['shared_at'],
        ];
    }

    private function findChoreList(int $id): array
    {
        $statement = $this->pdo->prepare(
            'SELECT cl.*, u.username AS owner_username
             FROM chore_lists cl
             JOIN users u ON u.id = cl.owner_id
             WHERE cl.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        if (!$row) {
            throw new HttpException(404, 'Chore list not found');
        }

        return $row;
    }

    private function ensureChoreListExists(int $id): void
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM chore_lists WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);

        if (!$statement->fetchColumn()) {
            throw new HttpException(404, 'Chore list not found');
        }
    }

    private function userExists(int $id): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);

        return (bool)$statement->fetchColumn();
    }

    private function exists(string $sql, array $params): bool
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

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
