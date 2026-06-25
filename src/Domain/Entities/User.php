<?php
declare(strict_types=1);

namespace App\Domain\Entities;

class User
{
    private ?int $id;
    private string $role;
    private string $name;
    private string $email;
    private string $passwordHash;
    private string $createdAt;

    public function __construct(
        ?int $id,
        string $role,
        string $name,
        string $email,
        string $passwordHash,
        string $createdAt = ''
    ) {
        $this->id = $id;
        $this->role = $role;
        $this->name = $name;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }
}
