<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entities\Comment;
use App\Domain\Repositories\CommentRepositoryInterface;
use PDO;

class PDOCommentRepository implements CommentRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByTaskId(int $taskId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM comments WHERE task_id = :task_id ORDER BY created_at ASC");
        $stmt->execute(['task_id' => $taskId]);
        $rows = $stmt->fetchAll();
        $comments = [];
        foreach ($rows as $row) {
            $comments[] = $this->mapRowToEntity($row);
        }
        return $comments;
    }

    public function save(Comment $comment): Comment
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO comments (task_id, user_id, message)
            VALUES (:task_id, :user_id, :message)
        ");
        $stmt->execute([
            'task_id' => $comment->getTaskId(),
            'user_id' => $comment->getUserId(),
            'message' => $comment->getMessage()
        ]);
        $id = (int)$this->pdo->lastInsertId();
        return new Comment(
            $id,
            $comment->getTaskId(),
            $comment->getUserId(),
            $comment->getMessage(),
            date('Y-m-d H:i:s')
        );
    }

    private function mapRowToEntity(array $row): Comment
    {
        return new Comment(
            (int)$row['id'],
            (int)$row['task_id'],
            (int)$row['user_id'],
            $row['message'],
            $row['created_at']
        );
    }
}
