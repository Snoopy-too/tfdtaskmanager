<?php
declare(strict_types=1);

namespace App\Domain\Entities;

class BgTemplateLayer
{
    private ?int $id;
    private int $templateId;
    private string $name;
    private string $layerType;
    private int $zIndex;
    private float $xPos;
    private float $yPos;
    private float $width;
    private float $height;
    private float $rotation;
    private float $opacity;
    private array $properties;
    private ?string $variableBinding;
    private bool $isVisible;
    private bool $isLocked;

    public function __construct(
        ?int $id,
        int $templateId,
        string $name,
        string $layerType,
        int $zIndex,
        float $xPos,
        float $yPos,
        float $width,
        float $height,
        float $rotation,
        float $opacity,
        array $properties,
        ?string $variableBinding,
        bool $isVisible = true,
        bool $isLocked = false
    ) {
        $this->id = $id;
        $this->templateId = $templateId;
        $this->name = $name;
        $this->layerType = $layerType;
        $this->zIndex = $zIndex;
        $this->xPos = $xPos;
        $this->yPos = $yPos;
        $this->width = $width;
        $this->height = $height;
        $this->rotation = $rotation;
        $this->opacity = $opacity;
        $this->properties = $properties;
        $this->variableBinding = $variableBinding;
        $this->isVisible = $isVisible;
        $this->isLocked = $isLocked;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTemplateId(): int
    {
        return $this->templateId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLayerType(): string
    {
        return $this->layerType;
    }

    public function getZIndex(): int
    {
        return $this->zIndex;
    }

    public function getXPos(): float
    {
        return $this->xPos;
    }

    public function getYPos(): float
    {
        return $this->yPos;
    }

    public function getWidth(): float
    {
        return $this->width;
    }

    public function getHeight(): float
    {
        return $this->height;
    }

    public function getRotation(): float
    {
        return $this->rotation;
    }

    public function getOpacity(): float
    {
        return $this->opacity;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getVariableBinding(): ?string
    {
        return $this->variableBinding;
    }

    public function isVisible(): bool
    {
        return $this->isVisible;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }
}
