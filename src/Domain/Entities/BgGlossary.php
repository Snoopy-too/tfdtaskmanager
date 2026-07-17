<?php
declare(strict_types=1);

namespace App\Domain\Entities;

class BgGlossary
{
    private ?int $id;
    private int $projectId;
    private string $termKey;
    private string $termName;
    private string $termDescription;
    private int $createdBy;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(
        ?int $id,
        int $projectId,
        string $termKey,
        string $termName,
        string $termDescription,
        int $createdBy,
        string $createdAt = '',
        string $updatedAt = ''
    ) {
        $this->id = $id;
        $this->projectId = $projectId;
        $this->termKey = $termKey;
        $this->termName = $termName;
        $this->termDescription = $termDescription;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProjectId(): int
    {
        return $this->projectId;
    }

    public function getTermKey(): string
    {
        return $this->termKey;
    }

    public function getTermName(): string
    {
        return $this->termName;
    }

    public function getTermDescription(): string
    {
        return $this->termDescription;
    }

    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): string
    {
        return $this->updatedAt;
    }
}
