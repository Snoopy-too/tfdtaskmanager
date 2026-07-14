<?php
declare(strict_types=1);

namespace App\Domain\Entities;

class BgDataset
{
    private ?int $id;
    private int $projectId;
    private string $name;
    private array $columnMap;
    private array $rowData;
    private int $createdBy;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(
        ?int $id,
        int $projectId,
        string $name,
        array $columnMap,
        array $rowData,
        int $createdBy,
        string $createdAt = '',
        string $updatedAt = ''
    ) {
        $this->id = $id;
        $this->projectId = $projectId;
        $this->name = $name;
        $this->columnMap = $columnMap;
        $this->rowData = $rowData;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getColumnMap(): array
    {
        return $this->columnMap;
    }

    public function getRowData(): array
    {
        return $this->rowData;
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
