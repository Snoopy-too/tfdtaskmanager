<?php
declare(strict_types=1);

namespace App\Domain\Entities;

class BgAsset
{
    private ?int $id;
    private int $projectId;
    private string $originalFilename;
    private string $storedFilename;
    private string $mimeType;
    private int $fileSizeBytes;
    private ?string $tag;
    private int $uploadedBy;
    private string $createdAt;

    public function __construct(
        ?int $id,
        int $projectId,
        string $originalFilename,
        string $storedFilename,
        string $mimeType,
        int $fileSizeBytes,
        ?string $tag,
        int $uploadedBy,
        string $createdAt = ''
    ) {
        $this->id = $id;
        $this->projectId = $projectId;
        $this->originalFilename = $originalFilename;
        $this->storedFilename = $storedFilename;
        $this->mimeType = $mimeType;
        $this->fileSizeBytes = $fileSizeBytes;
        $this->tag = $tag;
        $this->uploadedBy = $uploadedBy;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProjectId(): int
    {
        return $this->projectId;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getStoredFilename(): string
    {
        return $this->storedFilename;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getFileSizeBytes(): int
    {
        return $this->fileSizeBytes;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function getUploadedBy(): int
    {
        return $this->uploadedBy;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
}
