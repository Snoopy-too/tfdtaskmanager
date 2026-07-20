<?php
declare(strict_types=1);

namespace App\Domain\Entities;

class BgTemplate
{
    private ?int $id;
    private int $projectId;
    private int $componentTypeId;
    private string $name;
    private ?string $canvasJson;
    private int $canvasWidthPx;
    private int $canvasHeightPx;
    private float $bleedMm;
    private float $safeMarginMm;
    private ?int $datasetId;
    private int $createdBy;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(
        ?int $id,
        int $projectId,
        int $componentTypeId,
        string $name,
        int $canvasWidthPx,
        int $canvasHeightPx,
        float $bleedMm,
        float $safeMarginMm,
        ?int $datasetId,
        int $createdBy,
        string $createdAt = '',
        string $updatedAt = ''
    ) {
        $this->id = $id;
        $this->projectId = $projectId;
        $this->componentTypeId = $componentTypeId;
        $this->name = $name;
        $this->canvasWidthPx = $canvasWidthPx;
        $this->canvasHeightPx = $canvasHeightPx;
        $this->bleedMm = $bleedMm;
        $this->safeMarginMm = $safeMarginMm;
        $this->datasetId = $datasetId;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->canvasJson = null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProjectId(): int
    {
        return $this->projectId;
    }

    public function getComponentTypeId(): int
    {
        return $this->componentTypeId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCanvasJson(): ?string
    {
        return $this->canvasJson;
    }

    public function setCanvasJson(?string $canvasJson): void
    {
        $this->canvasJson = $canvasJson;
    }

    public function getCanvasWidthPx(): int
    {
        return $this->canvasWidthPx;
    }

    public function getCanvasHeightPx(): int
    {
        return $this->canvasHeightPx;
    }

    public function getBleedMm(): float
    {
        return $this->bleedMm;
    }

    public function getSafeMarginMm(): float
    {
        return $this->safeMarginMm;
    }

    public function getDatasetId(): ?int
    {
        return $this->datasetId;
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

    /**
     * Helper to calculate pixel dimensions from mm at a specific DPI.
     * 1 inch = 25.4 mm
     */
    public static function mmToPx(float $mm, int $dpi = 300): int
    {
        return (int) round(($mm / 25.4) * $dpi);
    }
    public static function pxToMm(int $px, int $dpi = 300): float
    {
        return ($px / $dpi) * 25.4;
    }

    private ?int $lockedByUserId = null;
    private ?string $lockedAt = null;
    private ?string $rowFilter = null;

    public function getLockedByUserId(): ?int
    {
        return $this->lockedByUserId;
    }

    public function setLockedByUserId(?int $lockedByUserId): void
    {
        $this->lockedByUserId = $lockedByUserId;
    }

    public function getLockedAt(): ?string
    {
        return $this->lockedAt;
    }

    public function setLockedAt(?string $lockedAt): void
    {
        $this->lockedAt = $lockedAt;
    }

    public function getRowFilter(): ?string
    {
        return $this->rowFilter;
    }

    public function setRowFilter(?string $rowFilter): void
    {
        $this->rowFilter = $rowFilter;
    }
}
