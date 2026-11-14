<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entities\BgSandbox;
use App\Domain\Repositories\BgSandboxRepositoryInterface;
use PDO;

final class PDOBgSandboxRepository implements BgSandboxRepositoryInterface
{
    private static bool $tablesChecked = false;

    public function __construct(private readonly PDO $pdo)
    {
    }

    private function ensureTablesExist(): void
    {
        if (self::$tablesChecked) {
            return;
        }
        self::$tablesChecked = true;
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `bg_sandboxes` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `project_id` INT NOT NULL,
                    `name` VARCHAR(150) NOT NULL,
                    `default_player_count` TINYINT UNSIGNED NOT NULL DEFAULT 4,
                    `table_state` JSON NOT NULL,
                    `starting_snapshot` JSON DEFAULT NULL,
                    `created_by` INT NOT NULL,
                    `locked_by_user_id` INT DEFAULT NULL,
                    `locked_at` TIMESTAMP NULL DEFAULT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT `fk_bg_sandboxes_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_bg_sandboxes_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
                    CONSTRAINT `fk_bg_sandboxes_locked_user` FOREIGN KEY (`locked_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
                    INDEX `idx_bg_sandboxes_project` (`project_id`, `updated_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `bg_sandbox_rooms` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `sandbox_id` INT NOT NULL,
                    `room_code` VARCHAR(32) NOT NULL UNIQUE,
                    `host_user_id` INT NOT NULL,
                    `room_state` JSON NOT NULL,
                    `presence_state` JSON NOT NULL,
                    `version` INT UNSIGNED NOT NULL DEFAULT 1,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT `fk_bg_sandbox_rooms_sandbox` FOREIGN KEY (`sandbox_id`) REFERENCES `bg_sandboxes` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_bg_sandbox_rooms_host` FOREIGN KEY (`host_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                    INDEX `idx_bg_sandbox_rooms_sandbox` (`sandbox_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `bg_sandbox_publications` (
                    `sandbox_id` INT PRIMARY KEY,
                    `lobby_game_id` VARCHAR(64) NOT NULL,
                    `published_version` INT UNSIGNED NOT NULL DEFAULT 1,
                    `description` TEXT DEFAULT NULL,
                    `min_players` TINYINT UNSIGNED NOT NULL DEFAULT 1,
                    `max_players` TINYINT UNSIGNED NOT NULL DEFAULT 4,
                    `last_published_by` INT DEFAULT NULL,
                    `published_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT `fk_bg_sandbox_publications_sandbox` FOREIGN KEY (`sandbox_id`) REFERENCES `bg_sandboxes` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_bg_sandbox_publications_user` FOREIGN KEY (`last_published_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } catch (\Throwable $e) {
            error_log('[PDOBgSandboxRepository] Table creation check notice: ' . $e->getMessage());
        }
    }

    public function findByProjectId(int $projectId): array
    {
        $this->ensureTablesExist();
        $stmt = $this->pdo->prepare('SELECT * FROM bg_sandboxes WHERE project_id = :project_id ORDER BY updated_at DESC, id DESC');
        $stmt->execute(['project_id' => $projectId]);
        return array_map(fn (array $row): BgSandbox => $this->mapRowToEntity($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findById(int $id): ?BgSandbox
    {
        $this->ensureTablesExist();
        $stmt = $this->pdo->prepare('SELECT * FROM bg_sandboxes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->mapRowToEntity($row) : null;
    }

    public function save(BgSandbox $sandbox): BgSandbox
    {
        $this->ensureTablesExist();
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

    public function delete(int $id): void
    {
        $this->ensureTablesExist();
        $stmt = $this->pdo->prepare('DELETE FROM bg_sandboxes WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function updateLock(int $id, ?int $userId, ?string $lockedAt): void
    {
        $this->ensureTablesExist();
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
        $this->ensureTablesExist();
        $existing = $this->findRoomBySandboxId($sandboxId);
        $stateJson = json_encode($initialState, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $presenceJson = json_encode(['cursors' => [], 'pings' => [], 'seats' => [], 'members' => []], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

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
        $this->ensureTablesExist();
        $stmt = $this->pdo->prepare('SELECT * FROM bg_sandbox_rooms WHERE sandbox_id = :sandbox_id LIMIT 1');
        $stmt->execute(['sandbox_id' => $sandboxId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->mapRoomRow($row) : null;
    }

    /** @return array<string,mixed>|null */
    public function findRoomByCode(string $roomCode): ?array
    {
        $this->ensureTablesExist();
        $cleanCode = strtoupper(trim($roomCode));
        if ($cleanCode === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM bg_sandbox_rooms WHERE room_code = :room_code LIMIT 1');
        $stmt->execute(['room_code' => $cleanCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->mapRoomRow($row) : null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function findRoomsByProjectId(int $projectId): array
    {
        $this->ensureTablesExist();
        $stmt = $this->pdo->prepare('
            SELECT r.sandbox_id, r.room_code, r.host_user_id, r.presence_state, r.version, r.updated_at, u.name AS host_user_name
            FROM bg_sandbox_rooms r
            INNER JOIN bg_sandboxes s ON s.id = r.sandbox_id
            LEFT JOIN users u ON u.id = r.host_user_id
            WHERE s.project_id = :project_id
        ');
        $stmt->execute(['project_id' => $projectId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $presence = $this->decodePresence((string)($row['presence_state'] ?? ''));
            $out[(int)$row['sandbox_id']] = [
                'sandboxId' => (int)$row['sandbox_id'],
                'roomCode' => (string)$row['room_code'],
                'hostUserId' => (int)$row['host_user_id'],
                'hostUserName' => (string)($row['host_user_name'] ?? ('User #' . (int)$row['host_user_id'])),
                'presenceState' => $presence,
                'version' => (int)$row['version'],
                'updatedAt' => (string)$row['updated_at'],
            ];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $roomState
     * @param array<string,mixed> $presenceState
     */
    public function updateRoom(int $roomId, array $roomState, array $presenceState, bool $bumpVersion = true): void
    {
        $this->ensureTablesExist();
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

    public function mutateRoom(int $sandboxId, callable $mutator): ?array
    {
        $this->ensureTablesExist();
        $startedTx = !$this->pdo->inTransaction();
        if ($startedTx) {
            $this->pdo->beginTransaction();
        }
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM bg_sandbox_rooms WHERE sandbox_id = :sandbox_id LIMIT 1 FOR UPDATE');
            $stmt->execute(['sandbox_id' => $sandboxId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                if ($startedTx && $this->pdo->inTransaction()) {
                    $this->pdo->commit();
                }
                return null;
            }

            $room = $this->mapRoomRow($row);
            $mutation = $mutator($room);
            $stateChanged = !empty($mutation['stateChanged']);
            $presenceChanged = !empty($mutation['presenceChanged']);
            $bumpVersion = !empty($mutation['bumpVersion']);

            $newRoomState = is_array($mutation['roomState'] ?? null) ? $mutation['roomState'] : $room['roomState'];
            $newPresence = is_array($mutation['presenceState'] ?? null) ? $mutation['presenceState'] : $room['presenceState'];

            $setClauses = [];
            $params = ['id' => $room['id']];
            if ($stateChanged) {
                $setClauses[] = 'room_state = :room_state';
                $params['room_state'] = json_encode($newRoomState, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
            if ($presenceChanged) {
                $setClauses[] = 'presence_state = :presence_state';
                $params['presence_state'] = json_encode($newPresence, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
            if ($bumpVersion) {
                $setClauses[] = 'version = version + 1';
            }

            if ($setClauses !== []) {
                $sql = 'UPDATE bg_sandbox_rooms SET ' . implode(', ', $setClauses) . ' WHERE id = :id';
                $upd = $this->pdo->prepare($sql);
                $upd->execute($params);
            }

            if ($startedTx && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            $room['roomState'] = $newRoomState;
            $room['presenceState'] = $newPresence;
            if ($bumpVersion) {
                $room['version'] = (int)$room['version'] + 1;
            }

            return [
                'room' => $room,
                'result' => $mutation['result'] ?? null,
            ];
        } catch (\Throwable $e) {
            if ($startedTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function deleteRoom(int $sandboxId): void
    {
        $this->ensureTablesExist();
        $stmt = $this->pdo->prepare('DELETE FROM bg_sandbox_rooms WHERE sandbox_id = :sandbox_id');
        $stmt->execute(['sandbox_id' => $sandboxId]);
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function mapRoomRow(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'sandboxId' => (int)$row['sandbox_id'],
            'roomCode' => (string)$row['room_code'],
            'hostUserId' => (int)$row['host_user_id'],
            'roomState' => json_decode((string)$row['room_state'], true) ?: [],
            'presenceState' => $this->decodePresence((string)($row['presence_state'] ?? '')),
            'version' => (int)$row['version'],
            'updatedAt' => (string)$row['updated_at'],
        ];
    }

    /** @return array<string,mixed> */
    private function decodePresence(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        return [
            'cursors' => is_array($decoded['cursors'] ?? null) ? $decoded['cursors'] : [],
            'pings' => is_array($decoded['pings'] ?? null) ? $decoded['pings'] : [],
            'seats' => is_array($decoded['seats'] ?? null) ? $decoded['seats'] : [],
            'members' => is_array($decoded['members'] ?? null) ? $decoded['members'] : [],
        ];
    }

    /** @param array<string,mixed> $row */
    private function mapRowToEntity(array $row): BgSandbox
    {
        $tableState = json_decode((string)$row['table_state'], true);
        $snapshot = $row['starting_snapshot'] !== null ? json_decode((string)$row['starting_snapshot'], true) : null;

        return new BgSandbox(
            (int)$row['id'],
            (int)$row['project_id'],
            (string)$row['name'],
            (int)$row['default_player_count'],
            is_array($tableState) ? $tableState : [],
            is_array($snapshot) ? $snapshot : null,
            (int)$row['created_by'],
            (string)$row['created_at'],
            (string)$row['updated_at'],
            $row['locked_by_user_id'] !== null ? (int)$row['locked_by_user_id'] : null,
            $row['locked_at'] !== null ? (string)$row['locked_at'] : null
        );
    }

    public function findPublicationBySandboxId(int $sandboxId): ?array
    {
        $this->ensureTablesExist();
        $stmt = $this->pdo->prepare('SELECT * FROM bg_sandbox_publications WHERE sandbox_id = :sandbox_id LIMIT 1');
        $stmt->execute(['sandbox_id' => $sandboxId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->mapPublicationRow($row) : null;
    }

    public function findPublicationsByProjectId(int $projectId): array
    {
        $this->ensureTablesExist();
        $stmt = $this->pdo->prepare(
            'SELECT p.* FROM bg_sandbox_publications p
             INNER JOIN bg_sandboxes s ON s.id = p.sandbox_id
             WHERE s.project_id = :project_id'
        );
        $stmt->execute(['project_id' => $projectId]);
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapped = $this->mapPublicationRow($row);
            $result[(int)$mapped['sandboxId']] = $mapped;
        }
        return $result;
    }

    public function savePublication(int $sandboxId, array $data): array
    {
        $this->ensureTablesExist();
        $stmt = $this->pdo->prepare('
            INSERT INTO bg_sandbox_publications
                (sandbox_id, lobby_game_id, published_version, description, min_players, max_players, last_published_by, published_at)
            VALUES
                (:sandbox_id, :lobby_game_id, :published_version, :description, :min_players, :max_players, :last_published_by, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                lobby_game_id = VALUES(lobby_game_id),
                published_version = VALUES(published_version),
                description = VALUES(description),
                min_players = VALUES(min_players),
                max_players = VALUES(max_players),
                last_published_by = VALUES(last_published_by),
                published_at = CURRENT_TIMESTAMP
        ');
        $stmt->execute([
            'sandbox_id' => $sandboxId,
            'lobby_game_id' => (string)$data['lobbyGameId'],
            'published_version' => (int)$data['publishedVersion'],
            'description' => (string)($data['description'] ?? ''),
            'min_players' => (int)$data['minPlayers'],
            'max_players' => (int)$data['maxPlayers'],
            'last_published_by' => $data['lastPublishedBy'] ?? null,
        ]);

        return $this->findPublicationBySandboxId($sandboxId) ?? [
            'sandboxId' => $sandboxId,
            'lobbyGameId' => (string)$data['lobbyGameId'],
            'publishedVersion' => (int)$data['publishedVersion'],
            'description' => (string)($data['description'] ?? ''),
            'minPlayers' => (int)$data['minPlayers'],
            'maxPlayers' => (int)$data['maxPlayers'],
            'lastPublishedBy' => $data['lastPublishedBy'] ?? null,
            'publishedAt' => date('Y-m-d H:i:s'),
        ];
    }

    public function deletePublication(int $sandboxId): void
    {
        $this->ensureTablesExist();
        $stmt = $this->pdo->prepare('DELETE FROM bg_sandbox_publications WHERE sandbox_id = :sandbox_id');
        $stmt->execute(['sandbox_id' => $sandboxId]);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function mapPublicationRow(array $row): array
    {
        return [
            'sandboxId' => (int)$row['sandbox_id'],
            'lobbyGameId' => (string)$row['lobby_game_id'],
            'publishedVersion' => (int)$row['published_version'],
            'description' => (string)($row['description'] ?? ''),
            'minPlayers' => (int)$row['min_players'],
            'maxPlayers' => (int)$row['max_players'],
            'lastPublishedBy' => $row['last_published_by'] !== null ? (int)$row['last_published_by'] : null,
            'publishedAt' => (string)$row['published_at'],
            'updatedAt' => (string)$row['updated_at'],
        ];
    }
}
