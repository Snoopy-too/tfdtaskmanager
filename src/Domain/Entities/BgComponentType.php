<?php
declare(strict_types=1);

namespace App\Domain\Entities;

class BgComponentType
{
    private ?int $id;
    private string $name;
    private float $widthMm;
    private float $heightMm;
    private ?string $description;

    public function __construct(
        ?int $id,
        string $name,
        float $widthMm,
        float $heightMm,
        ?string $description
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->widthMm = $widthMm;
        $this->heightMm = $heightMm;
        $this->description = $description;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getWidthMm(): float
    {
        return $this->widthMm;
    }

    public function getHeightMm(): float
    {
        return $this->heightMm;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
