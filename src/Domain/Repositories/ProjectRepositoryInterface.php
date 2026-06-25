<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\Project;

interface ProjectRepositoryInterface
{
    public function findById(int $id): ?Project;
    
    public function save(Project $project): Project;
    
    public function delete(int $id): void;
    
    /**
     * @return Project[]
     */
    public function findAll(): array;
}
