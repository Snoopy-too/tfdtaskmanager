<?php
declare(strict_types=1);

namespace App\Domain\Entities;

class TaskHistory
{
    private ?int $id;
    private int $taskId;
    private int $userId;
    private string $action; // 'created', 'checked_out', 'checked_in', 'completed'
    private ?string $note;
    private string $createdAt;

    public function __construct(
        ?int $id,
        int $taskId,
        int $userId,
        string $action,
        ?string $note,
        string $createdAt = ''
    ) {
        $this->id = $id;
        $this->taskId = $taskId;
        $this->userId = $userId;
        $this->action = $action;
        $this->note = $note;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaskId(): int
    {
        return $this->taskId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
}
