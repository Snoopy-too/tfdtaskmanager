<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\Task;

interface TaskRepositoryInterface
{
    public function findById(int $id): ?Task;
    
    public function save(Task $task): Task;
    
    public function delete(int $id): void;
    
    /**
     * @return Task[]
     */
    public function findAll(): array;
    
    /**
     * @return Task[]
     */
    public function findByFilters(?int $projectId, ?string $status, bool $onlyBugs = false, ?string $sortBy = null): array;
}
