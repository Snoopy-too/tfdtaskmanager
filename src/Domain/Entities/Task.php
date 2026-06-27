<?php
declare(strict_types=1);

namespace App\Domain\Entities;

class Task
{
    private ?int $id;
    private int $projectId;
    private string $title;
    private string $details;
    private string $status; // 'To Do', 'In Progress', 'Done'
    private ?string $deadline;
    private int $createdBy;
    private ?int $assignedTo;
    private ?string $checkedOutAt;
    private string $createdAt;
    private bool $isBug;

    public function __construct(
        ?int $id,
        int $projectId,
        string $title,
        string $details,
        string $status,
        ?string $deadline,
        int $createdBy,
        ?int $assignedTo = null,
        ?string $checkedOutAt = null,
        string $createdAt = '',
        bool $isBug = false
    ) {
        $this->id = $id;
        $this->projectId = $projectId;
        $this->title = $title;
        $this->details = $details;
        $this->status = $status;
        $this->deadline = $deadline;
        $this->createdBy = $createdBy;
        $this->assignedTo = $assignedTo;
        $this->checkedOutAt = $checkedOutAt;
        $this->createdAt = $createdAt;
        $this->isBug = $isBug;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProjectId(): int
    {
        return $this->projectId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDetails(): string
    {
        return $this->details;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getDeadline(): ?string
    {
        return $this->deadline;
    }

    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    public function getAssignedTo(): ?int
    {
        return $this->assignedTo;
    }

    public function getCheckedOutAt(): ?string
    {
        return $this->checkedOutAt;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function isBug(): bool
    {
        return $this->isBug;
    }

    // Business Logic for Task checkout/check-in transitions
    public function checkout(int $userId): void
    {
        if ($this->status !== 'To Do') {
            throw new \LogicException("Only tasks in 'To Do' status can be checked out.");
        }
        $this->status = 'In Progress';
        $this->assignedTo = $userId;
        $this->checkedOutAt = date('Y-m-d H:i:s');
    }

    public function checkin(): void
    {
        if ($this->status !== 'In Progress') {
            throw new \LogicException("Only tasks in 'In Progress' status can be checked in.");
        }
        $this->status = 'To Do';
        $this->assignedTo = null;
        $this->checkedOutAt = null;
    }

    public function complete(): void
    {
        $this->status = 'Done';
        $this->assignedTo = null;
        $this->checkedOutAt = null;
    }
}
