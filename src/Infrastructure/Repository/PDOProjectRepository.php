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
        $stmt = $this->pdo->prepare("SELECT * FROM projects WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return $this->mapRowToEntity($row);
    }

    public function save(Project $project): Project
    {
        if ($project->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO projects (name, description)
                VALUES (:name, :description)
            ");
            $stmt->execute([
                'name' => $project->getName(),
                'description' => $project->getDescription()
            ]);
            $id = (int)$this->pdo->lastInsertId();
            return new Project(
                $id,
                $project->getName(),
                $project->getDescription(),
                date('Y-m-d H:i:s')
            );
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE projects
                SET name = :name, description = :description
                WHERE id = :id
            ");
            $stmt->execute([
                'name' => $project->getName(),
                'description' => $project->getDescription(),
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

    public function findAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM projects ORDER BY name ASC");
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
            $row['created_at']
        );
    }
}
