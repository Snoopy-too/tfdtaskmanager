<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entities\Project;
use App\Domain\Repositories\ProjectRepositoryInterface;
use PDO;

class PDOProjectRepository implements ProjectRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?Project
    {
        $this->ensureProjectColumns();
        $stmt = $this->pdo->prepare("SELECT * FROM projects WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return $this->mapRowToEntity($row);
    }

    public function save(Project $project): Project
    {
        $this->ensureProjectColumns();
        if ($project->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO projects (name, description, is_private, created_by)
                VALUES (:name, :description, :is_private, :created_by)
            ");
            $stmt->execute([
                'name' => $project->getName(),
                'description' => $project->getDescription(),
                'is_private' => $project->isPrivate() ? 1 : 0,
                'created_by' => $project->getCreatedBy()
            ]);
            $id = (int)$this->pdo->lastInsertId();
            return new Project(
                $id,
                $project->getName(),
                $project->getDescription(),
                date('Y-m-d H:i:s'),
                $project->isPrivate(),
                $project->getCreatedBy()
            );
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE projects
                SET name = :name, description = :description, is_private = :is_private, created_by = :created_by
                WHERE id = :id
            ");
            $stmt->execute([
                'name' => $project->getName(),
                'description' => $project->getDescription(),
                'is_private' => $project->isPrivate() ? 1 : 0,
                'created_by' => $project->getCreatedBy(),
                'id' => $project->getId()
            ]);
            return $project;
        }
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM projects WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function findAll(?int $forUserId = null): array
    {
        $this->ensureProjectColumns();
        if ($forUserId !== null && $forUserId > 0) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM projects 
                WHERE is_private = 0 OR (is_private = 1 AND created_by = :for_user_id) 
                ORDER BY name ASC
            ");
            $stmt->execute(['for_user_id' => $forUserId]);
        } else {
            $stmt = $this->pdo->query("SELECT * FROM projects ORDER BY name ASC");
        }
        $rows = $stmt->fetchAll();
        $projects = [];
        foreach ($rows as $row) {
            $projects[] = $this->mapRowToEntity($row);
        }
        return $projects;
    }

    private function mapRowToEntity(array $row): Project
    {
        return new Project(
            (int)$row['id'],
            $row['name'],
            $row['description'] ?? '',
            $row['created_at'] ?? '',
            (bool)($row['is_private'] ?? false),
            isset($row['created_by']) && $row['created_by'] !== null ? (int)$row['created_by'] : null
        );
    }

    private function ensureProjectColumns(): void
    {
        static $checked = false;
        if ($checked) return;
        try {
            $cols = $this->pdo->query("SHOW COLUMNS FROM `projects` LIKE 'is_private'")->fetchAll();
            if (empty($cols)) {
                $this->pdo->exec("ALTER TABLE `projects` ADD COLUMN `is_private` TINYINT(1) NOT NULL DEFAULT 0 AFTER `description`");
            }
            $createdByCols = $this->pdo->query("SHOW COLUMNS FROM `projects` LIKE 'created_by'")->fetchAll();
            if (empty($createdByCols)) {
                $this->pdo->exec("ALTER TABLE `projects` ADD COLUMN `created_by` INT DEFAULT NULL AFTER `is_private`");
                $this->pdo->exec("ALTER TABLE `projects` ADD CONSTRAINT `fk_projects_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL");
            }
        } catch (\Throwable $e) {
            // Ignore if already exists or during concurrent calls
        }
        $checked = true;
    }
}
