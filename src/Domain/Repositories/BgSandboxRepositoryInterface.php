<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\BgSandbox;

interface BgSandboxRepositoryInterface
{
    /**
     * @return BgSandbox[] Ordered by most recently modified first.
     */
    public function findByProjectId(int $projectId): array;

    public function findById(int $id): ?BgSandbox;

    public function save(BgSandbox $sandbox): BgSandbox;

    public function delete(int $id): void;

    public function updateLock(int $id, ?int $userId, ?string $lockedAt): void;

    /**
     * @param array<string,mixed> $initialState
     * @return array<string,mixed>
     */
    public function createOrResetRoom(int $sandboxId, int $hostUserId, array $initialState): array;

    /** @return array<string,mixed>|null */
    public function findRoomBySandboxId(int $sandboxId): ?array;

    /** @return array<string,mixed>|null */
    public function findRoomByCode(string $roomCode): ?array;

    /**
     * Returns lightweight active room summaries keyed by `sandboxId` for a project.
     *
     * @return array<int,array<string,mixed>>
     */
    public function findRoomsByProjectId(int $projectId): array;

    /**
     * @param array<string,mixed> $roomState
     * @param array<string,mixed> $presenceState
     */
    public function updateRoom(int $roomId, array $roomState, array $presenceState, bool $bumpVersion = true): void;

    /**
     * Atomically locks the active room row for the given sandbox, invokes `$mutator($room)`,
     * and persists only the columns marked changed.
     *
     * @param callable(array<string,mixed>): array<string,mixed> $mutator
     * @return array{room:array<string,mixed>,result:mixed}|null
     */
    public function mutateRoom(int $sandboxId, callable $mutator): ?array;

    public function deleteRoom(int $sandboxId): void;

    /** @return array<string,mixed>|null */
    public function findPublicationBySandboxId(int $sandboxId): ?array;

    /**
     * Returns publication records keyed by `sandboxId` for all sandboxes in a project.
     *
     * @return array<int,array<string,mixed>>
     */
    public function findPublicationsByProjectId(int $projectId): array;

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function savePublication(int $sandboxId, array $data): array;

    public function deletePublication(int $sandboxId): void;
}
