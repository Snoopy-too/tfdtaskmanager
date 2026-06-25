<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entities\TaskHistory;
use App\Domain\Repositories\TaskHistoryRepositoryInterface;
use PDO;

class PDOTaskHistoryRepository implements TaskHistoryRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByTaskId(int $taskId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM task_history WHERE task_id = :task_id ORDER BY created_at DESC");
        $stmt->execute(['task_id' => $taskId]);
        $rows = $stmt->fetchAll();
        $history = [];
        foreach ($rows as $row) {
            $history[] = $this->mapRowToEntity($row);
        }
        return $history;
    }

    public function save(TaskHistory $history): TaskHistory
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO task_history (task_id, user_id, action, note)
            VALUES (:task_id, :user_id, :action, :note)
        ");
        $stmt->execute([
            'task_id' => $history->getTaskId(),
            'user_id' => $history->getUserId(),
            'action' => $history->getAction(),
            'note' => $history->getNote()
        ]);
        $id = (int)$this->pdo->lastInsertId();
        return new TaskHistory(
            $id,
            $history->getTaskId(),
            $history->getUserId(),
            $history->getAction(),
            $history->getNote(),
            date('Y-m-d H:i:s')
        );
    }

    private function mapRowToEntity(array $row): TaskHistory
    {
        return new TaskHistory(
            (int)$row['id'],
            (int)$row['task_id'],
            (int)$row['user_id'],
            $row['action'],
            $row['note'] ?? null,
            $row['created_at']
        );
    }
}
