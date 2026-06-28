<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\Meeting;

interface MeetingRepositoryInterface
{
    public function findById(int $id): ?Meeting;
    
    public function save(Meeting $meeting): Meeting;
    
    /**
     * @return Meeting[]
     */
    public function findAll(): array;
}
