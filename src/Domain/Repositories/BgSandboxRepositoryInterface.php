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

    /**
     * @param array<string,mixed> $roomState
     * @param array<string,mixed> $presenceState
     */
    public function updateRoom(int $roomId, array $roomState, array $presenceState, bool $bumpVersion = true): void;
}
