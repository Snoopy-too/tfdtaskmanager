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
    public const MIN_PLAYERS = 1;
    public const MAX_PLAYERS = 6;
    public const MAX_NAME_LENGTH = 150;
    public const LOCK_TTL_SECONDS = 60;

    /**
     * @param array<string,mixed>      $tableState
     * @param array<string,mixed>|null $startingSnapshot
     */
    public function __construct(
        private ?int $id,
        private int $projectId,
        private string $name,
        private int $defaultPlayerCount,
        private array $tableState,
        private ?array $startingSnapshot,
        private int $createdBy,
        private string $createdAt = '',
        private string $updatedAt = '',
        private ?int $lockedByUserId = null,
        private ?string $lockedAt = null
    ) {
    }

    public function __get(string $property): mixed
    {
        return match ($property) {
            'id' => $this->id,
            'projectId' => $this->projectId,
            'name' => $this->name,
            'defaultPlayerCount' => $this->defaultPlayerCount,
            'tableState' => $this->tableState,
            'startingSnapshot' => $this->startingSnapshot,
            'createdBy' => $this->createdBy,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'lockedByUserId' => $this->lockedByUserId,
            'lockedAt' => $this->lockedAt,
            default => null,
        };
    }

    public function getId(): ?int { return $this->id; }
    public function getProjectId(): int { return $this->projectId; }
    public function getName(): string { return $this->name; }
    public function getDefaultPlayerCount(): int { return $this->defaultPlayerCount; }
    public function getTableState(): array { return $this->tableState; }
    public function getStartingSnapshot(): ?array { return $this->startingSnapshot; }
    public function getCreatedBy(): int { return $this->createdBy; }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): string { return $this->updatedAt; }
    public function getLockedByUserId(): ?int { return $this->lockedByUserId; }
    public function getLockedAt(): ?string { return $this->lockedAt; }

    /** @param array<string,mixed> $tableState */
    public function withTableState(array $tableState): self
    {
        $clone = clone $this;
        $clone->tableState = $tableState;
        return $clone;
    }

    /** @param array<string,mixed>|null $startingSnapshot */
    public function withStartingSnapshot(?array $startingSnapshot): self
    {
        $clone = clone $this;
        $clone->startingSnapshot = $startingSnapshot;
        return $clone;
    }

    public function withId(int $id): self
    {
        $clone = clone $this;
        $clone->id = $id;
        return $clone;
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
