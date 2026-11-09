<?php
declare(strict_types=1);

namespace App\Domain\Entities;

/**
 * A Playtest Sandbox: a saved 2D tabletop layout belonging to a Board Game Studio project.
 *
 * Identity fields (id, projectId, name) live in columns and are injected into the
 * client-facing state on output; `tableState` holds only the normalized table content.
 */
final class BgSandbox
{
    public const int MIN_PLAYERS = 1;
    public const int MAX_PLAYERS = 6;
    public const int MAX_NAME_LENGTH = 150;
    public const int LOCK_TTL_SECONDS = 60;

    /**
     * @param array<string,mixed>      $tableState
     * @param array<string,mixed>|null $startingSnapshot
     */
    public function __construct(
        public private(set) ?int $id,
        public private(set) int $projectId,
        public private(set) string $name,
        public private(set) int $defaultPlayerCount,
        public private(set) array $tableState,
        public private(set) ?array $startingSnapshot,
        public private(set) int $createdBy,
        public private(set) string $createdAt = '',
        public private(set) string $updatedAt = '',
        public private(set) ?int $lockedByUserId = null,
        public private(set) ?string $lockedAt = null,
    ) {
    }

    /** @param array<string,mixed> $tableState */
    public function withTableState(array $tableState): self
    {
        return clone($this, ['tableState' => $tableState]);
    }

    /** @param array<string,mixed>|null $startingSnapshot */
    public function withStartingSnapshot(?array $startingSnapshot): self
    {
        return clone($this, ['startingSnapshot' => $startingSnapshot]);
    }

    public function withId(int $id): self
    {
        return clone($this, ['id' => $id]);
    }

    /**
     * True when another user holds a non-expired edit lock.
     */
    public function isLockedByOther(int $userId, ?int $now = null): bool
    {
        if ($this->lockedByUserId === null || $this->lockedByUserId === $userId) {
            return false;
        }
        $lockedTime = strtotime($this->lockedAt ?? '');
        if ($lockedTime === false) {
            return false;
        }
        return (($now ?? time()) - $lockedTime) < self::LOCK_TTL_SECONDS;
    }
}
