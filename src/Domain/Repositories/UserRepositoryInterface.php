<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;
    
    public function findByEmail(string $email): ?User;
    
    public function save(User $user): User;
    
    public function delete(int $id): void;
    
    /**
     * @return User[]
     */
    public function findAll(): array;
    
    public function countMembers(): int;
}
