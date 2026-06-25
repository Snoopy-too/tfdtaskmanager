<?php
declare(strict_types=1);

namespace App\Domain\Entities;

class Project
{
    private ?int $id;
    private string $name;
    private string $description;
    private string $createdAt;

    public function __construct(
        ?int $id,
        string $name,
        string $description,
        string $createdAt = ''
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
}
