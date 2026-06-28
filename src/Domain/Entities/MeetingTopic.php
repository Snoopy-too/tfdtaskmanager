<?php
declare(strict_types=1);

namespace App\Domain\Entities;

class MeetingTopic
{
    private ?int $id;
    private int $meetingId;
    private int $userId;
    private string $title;
    private string $createdAt;

    public function __construct(
        ?int $id,
        int $meetingId,
        int $userId,
        string $title,
        string $createdAt = ''
    ) {
        $this->id = $id;
        $this->meetingId = $meetingId;
        $this->userId = $userId;
        $this->title = $title;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMeetingId(): int
    {
        return $this->meetingId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
}
