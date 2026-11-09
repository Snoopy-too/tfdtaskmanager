<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entities\BgSandbox;
use App\Domain\Repositories\BgSandboxRepositoryInterface;
use PDO;

final class PDOBgSandboxRepository implements BgSandboxRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    #[\Override]
    public function findByProjectId(int $projectId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bg_sandboxes WHERE project_id = :project_id ORDER BY updated_at DESC, id DESC');
        $stmt->execute(['project_id' => $projectId]);
        return array_map(fn (array $row): BgSandbox => $this->mapRowToEntity($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    #[\Override]
    public function findById(int $id): ?BgSandbox
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bg_sandboxes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->mapRowToEntity($row) : null;
    }

    #[\Override]
    public function save(BgSandbox $sandbox): BgSandbox
    {
        $params = [
            'name' => $sandbox->name,
            'default_player_count' => $sandbox->defaultPlayerCount,
            'table_state' => json_encode($sandbox->tableState, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'starting_snapshot' => $sandbox->startingSnapshot === null
                ? null
                : json_encode($sandbox->startingSnapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];

        if ($sandbox->id === null) {
            $stmt = $this->pdo->prepare('
                INSERT INTO bg_sandboxes (project_id, name, default_player_count, table_state, starting_snapshot, created_by)
                VALUES (:project_id, :name, :default_player_count, :table_state, :starting_snapshot, :created_by)
            ');
            $stmt->execute($params + ['project_id' => $sandbox->projectId, 'created_by' => $sandbox->createdBy]);
            return $this->findById((int)$this->pdo->lastInsertId()) ?? $sandbox;
        }

        $stmt = $this->pdo->prepare('
            UPDATE bg_sandboxes
            SET name = :name, default_player_count = :default_player_count,
                table_state = :table_state, starting_snapshot = :starting_snapshot
            WHERE id = :id
        ');
        $stmt->execute($params + ['id' => $sandbox->id]);
        return $this->findById($sandbox->id) ?? $sandbox;
    }

    #[\Override]
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM bg_sandboxes WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    #[\Override]
    public function updateLock(int $id, ?int $userId, ?string $lockedAt): void
    {
        $stmt = $this->pdo->prepare('UPDATE bg_sandboxes SET locked_by_user_id = :user_id, locked_at = :locked_at WHERE id = :id');
        $stmt->execute(['id' => $id, 'user_id' => $userId, 'locked_at' => $lockedAt]);
    }

    /**
     * Creates or refreshes a live multiplayer room for the given sandbox.
     *
     * @param array<string,mixed> $initialState
     * @return array<string,mixed>
     */
    public function createOrResetRoom(int $sandboxId, int $hostUserId, array $initialState): array
    {
        $existing = $this->findRoomBySandboxId($sandboxId);
        $stateJson = json_encode($initialState, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $presenceJson = json_encode(['cursors' => [], 'pings' => [], 'seats' => []], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if ($existing !== null) {
            $stmt = $this->pdo->prepare('
                UPDATE bg_sandbox_rooms
                SET host_user_id = :host_user_id, room_state = :room_state, presence_state = :presence_state, version = version + 1
                WHERE id = :id
            ');
            $stmt->execute([
                'host_user_id' => $hostUserId,
                'room_state' => $stateJson,
                'presence_state' => $presenceJson,
                'id' => $existing['id'],
            ]);
            return $this->findRoomBySandboxId($sandboxId) ?? $existing;
        }

        $code = strtoupper(bin2hex(random_bytes(4)));
        $stmt = $this->pdo->prepare('
            INSERT INTO bg_sandbox_rooms (sandbox_id, room_code, host_user_id, room_state, presence_state, version)
            VALUES (:sandbox_id, :room_code, :host_user_id, :room_state, :presence_state, 1)
        ');
        $stmt->execute([
            'sandbox_id' => $sandboxId,
            'room_code' => $code,
            'host_user_id' => $hostUserId,
            'room_state' => $stateJson,
            'presence_state' => $presenceJson,
        ]);
        return $this->findRoomBySandboxId($sandboxId) ?? [];
    }

    /** @return array<string,mixed>|null */
    public function findRoomBySandboxId(int $sandboxId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bg_sandbox_rooms WHERE sandbox_id = :sandbox_id LIMIT 1');
        $stmt->execute(['sandbox_id' => $sandboxId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'id' => (int)$row['id'],
            'sandboxId' => (int)$row['sandbox_id'],
            'roomCode' => (string)$row['room_code'],
            'hostUserId' => (int)$row['host_user_id'],
            'roomState' => json_decode((string)$row['room_state'], true) ?: [],
            'presenceState' => json_decode((string)$row['presence_state'], true) ?: ['cursors' => [], 'pings' => [], 'seats' => []],
            'version' => (int)$row['version'],
            'updatedAt' => (string)$row['updated_at'],
        ];
    }

    /**
     * @param array<string,mixed> $roomState
     * @param array<string,mixed> $presenceState
     */
    public function updateRoom(int $roomId, array $roomState, array $presenceState, bool $bumpVersion = true): void
    {
        $sql = $bumpVersion
            ? 'UPDATE bg_sandbox_rooms SET room_state = :room_state, presence_state = :presence_state, version = version + 1 WHERE id = :id'
            : 'UPDATE bg_sandbox_rooms SET room_state = :room_state, presence_state = :presence_state WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'room_state' => json_encode($roomState, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'presence_state' => json_encode($presenceState, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'id' => $roomId,
        ]);
    }

    /** @param array<string,mixed> $row */
    private function mapRowToEntity(array $row): BgSandbox
    {
        $tableState = json_decode((string)$row['table_state'], true);
        $snapshot = $row['starting_snapshot'] !== null ? json_decode((string)$row['starting_snapshot'], true) : null;

        return new BgSandbox(
            id: (int)$row['id'],
            projectId: (int)$row['project_id'],
            name: (string)$row['name'],
            defaultPlayerCount: (int)$row['default_player_count'],
            tableState: is_array($tableState) ? $tableState : [],
            startingSnapshot: is_array($snapshot) ? $snapshot : null,
            createdBy: (int)$row['created_by'],
            createdAt: (string)$row['created_at'],
            updatedAt: (string)$row['updated_at'],
            lockedByUserId: $row['locked_by_user_id'] !== null ? (int)$row['locked_by_user_id'] : null,
            lockedAt: $row['locked_at'] !== null ? (string)$row['locked_at'] : null,
        );
    }
}
