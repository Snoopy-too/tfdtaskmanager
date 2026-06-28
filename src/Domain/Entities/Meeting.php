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

    public function __construct(
        ?int $id,
        string $title,
        ?string $scheduledDate,
        int $createdBy,
        string $createdAt = ''
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->scheduledDate = $scheduledDate;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
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
}
