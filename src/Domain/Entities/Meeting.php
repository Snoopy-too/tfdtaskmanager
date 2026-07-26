<?php
declare(strict_types=1);

namespace App\Domain\Entities;

class Meeting
{
    private ?int $id;
    private string $title;
    private ?string $scheduledDate;
    private int $createdBy;
    private string $createdAt;
    private string $status;
    private ?string $notes;

    public function __construct(
        ?int $id,
        string $title,
        ?string $scheduledDate,
        int $createdBy,
        string $createdAt = '',
        string $status = 'Pending',
        ?string $notes = null
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->scheduledDate = $scheduledDate;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->status = $status;
        $this->notes = $notes;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getScheduledDate(): ?string
    {
        return $this->scheduledDate;
    }

    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function isFinished(): bool
    {
        return $this->status === 'Finished';
    }
}
