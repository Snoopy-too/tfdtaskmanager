<?php
declare(strict_types=1);

namespace App\Domain\Entities;

class Comment
{
    private ?int $id;
    private int $taskId;
    private int $userId;
    private string $message;
    private string $createdAt;

    public function __construct(
        ?int $id,
        int $taskId,
        int $userId,
        string $message,
        string $createdAt = ''
    ) {
        $this->id = $id;
        $this->taskId = $taskId;
        $this->userId = $userId;
        $this->message = $message;
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

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
}
