<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\TaskHistory;

interface TaskHistoryRepositoryInterface
{
    /**
     * @return TaskHistory[]
     */
    public function findByTaskId(int $taskId): array;
    
    public function save(TaskHistory $history): TaskHistory;
}
