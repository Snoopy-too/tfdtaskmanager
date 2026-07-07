<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entities\Task;
use App\Domain\Repositories\TaskRepositoryInterface;
use PDO;

class PDOTaskRepository implements TaskRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?Task
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tasks WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return $this->mapRowToEntity($row);
    }

    public function save(Task $task): Task
    {
        if ($task->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO tasks (project_id, title, details, status, deadline, created_by, assigned_to, checked_out_at, is_bug)
                VALUES (:project_id, :title, :details, :status, :deadline, :created_by, :assigned_to, :checked_out_at, :is_bug)
            ");
            $stmt->execute([
                'project_id' => $task->getProjectId(),
                'title' => $task->getTitle(),
                'details' => $task->getDetails(),
                'status' => $task->getStatus(),
                'deadline' => $task->getDeadline(),
                'created_by' => $task->getCreatedBy(),
                'assigned_to' => $task->getAssignedTo(),
                'checked_out_at' => $task->getCheckedOutAt(),
                'is_bug' => $task->isBug() ? 1 : 0
            ]);
            $id = (int)$this->pdo->lastInsertId();
            return new Task(
                $id,
                $task->getProjectId(),
                $task->getTitle(),
                $task->getDetails(),
                $task->getStatus(),
                $task->getDeadline(),
                $task->getCreatedBy(),
                $task->getAssignedTo(),
                $task->getCheckedOutAt(),
                date('Y-m-d H:i:s'),
                $task->isBug()
            );
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE tasks
                SET project_id = :project_id, title = :title, details = :details, status = :status,
                    deadline = :deadline, created_by = :created_by, assigned_to = :assigned_to,
                    checked_out_at = :checked_out_at, is_bug = :is_bug
                WHERE id = :id
            ");
            $stmt->execute([
                'project_id' => $task->getProjectId(),
                'title' => $task->getTitle(),
                'details' => $task->getDetails(),
                'status' => $task->getStatus(),
                'deadline' => $task->getDeadline(),
                'created_by' => $task->getCreatedBy(),
                'assigned_to' => $task->getAssignedTo(),
                'checked_out_at' => $task->getCheckedOutAt(),
                'is_bug' => $task->isBug() ? 1 : 0,
                'id' => $task->getId()
            ]);
            return $task;
        }
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM tasks WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM tasks ORDER BY created_at DESC");
        $rows = $stmt->fetchAll();
        $tasks = [];
        foreach ($rows as $row) {
            $tasks[] = $this->mapRowToEntity($row);
        }
        return $tasks;
    }

    public function findByFilters(?int $projectId, ?string $status, bool $onlyBugs = false, ?string $sortBy = null): array
    {
        $sql = "SELECT * FROM tasks WHERE 1=1";
        $params = [];

        if ($projectId !== null) {
            $sql .= " AND project_id = :project_id";
            $params['project_id'] = $projectId;
        }

        if ($status !== null && $status !== '') {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }

        if ($onlyBugs) {
            $sql .= " AND is_bug = 1";
        }

        if ($sortBy === 'deadline') {
            $sql .= " ORDER BY (deadline IS NULL), deadline ASC, title ASC";
        } elseif ($sortBy === 'alphabetical') {
            $sql .= " ORDER BY title ASC";
        } elseif ($sortBy === 'task_number') {
            $sql .= " ORDER BY id ASC";
        } else {
            $sql .= " ORDER BY created_at DESC";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $tasks = [];
        foreach ($rows as $row) {
            $tasks[] = $this->mapRowToEntity($row);
        }
        return $tasks;
    }

    private function mapRowToEntity(array $row): Task
    {
        return new Task(
            (int)$row['id'],
            (int)$row['project_id'],
            $row['title'],
            $row['details'] ?? '',
            $row['status'],
            $row['deadline'] ? (string)$row['deadline'] : null,
            (int)$row['created_by'],
            $row['assigned_to'] ? (int)$row['assigned_to'] : null,
            $row['checked_out_at'] ? (string)$row['checked_out_at'] : null,
            $row['created_at'],
            (bool)($row['is_bug'] ?? false)
        );
    }
}
