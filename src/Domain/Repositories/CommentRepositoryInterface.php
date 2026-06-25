<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\Comment;

interface CommentRepositoryInterface
{
    /**
     * @return Comment[]
     */
    public function findByTaskId(int $taskId): array;
    
    public function save(Comment $comment): Comment;
}
