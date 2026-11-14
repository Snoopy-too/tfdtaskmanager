<?php
declare(strict_types=1);

namespace App\Domain\Entities;

class Project
{
    private ?int $id;
    private string $name;
    private string $description;
    private string $createdAt;
    private bool $isPrivate;
    private ?int $createdBy;
    private bool $isBoardGame;
    private string $type;

    public function __construct(
        ?int $id,
        string $name,
        string $description,
        string $createdAt = '',
        bool $isPrivate = false,
        ?int $createdBy = null,
        bool $isBoardGame = false,
        string $type = 'standard'
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->createdAt = $createdAt;
        $this->isPrivate = $isPrivate;
        $this->createdBy = $createdBy;
        if ($isBoardGame || $type === 'boardgame') {
            $isBoardGame = true;
            $type = 'boardgame';
        }
        $this->isBoardGame = $isBoardGame;
        $this->type = $type;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function isPrivate(): bool
    {
        return $this->isPrivate;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function isBoardGame(): bool
    {
        return $this->isBoardGame;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isAccessibleBy(?int $userId): bool
    {
        if (!$this->isPrivate) {
            return true;
        }
        if ($userId === null || $userId <= 0) {
            return false;
        }
        return $this->createdBy !== null && $this->createdBy === $userId;
    }
}
