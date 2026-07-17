<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entities\BgGlossary;
use App\Domain\Repositories\BgGlossaryRepositoryInterface;
use PDO;

class PDOBgGlossaryRepository implements BgGlossaryRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByProjectId(int $projectId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bg_glossary WHERE project_id = :project_id ORDER BY term_name ASC");
        $stmt->execute(['project_id' => $projectId]);
        $rows = $stmt->fetchAll();
        $glossary = [];
        foreach ($rows as $row) {
            $glossary[] = $this->mapRowToEntity($row);
        }
        return $glossary;
    }

    public function findById(int $id): ?BgGlossary
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bg_glossary WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return $this->mapRowToEntity($row);
    }

    public function findByTermKey(int $projectId, string $termKey): ?BgGlossary
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bg_glossary WHERE project_id = :project_id AND term_key = :term_key");
        $stmt->execute([
            'project_id' => $projectId,
            'term_key' => $termKey
        ]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return $this->mapRowToEntity($row);
    }

    public function save(BgGlossary $glossary): BgGlossary
    {
        if ($glossary->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO bg_glossary (project_id, term_key, term_name, term_description, created_by)
                VALUES (:project_id, :term_key, :term_name, :term_description, :created_by)
            ");
            $stmt->execute([
                'project_id' => $glossary->getProjectId(),
                'term_key' => $glossary->getTermKey(),
                'term_name' => $glossary->getTermName(),
                'term_description' => $glossary->getTermDescription(),
                'created_by' => $glossary->getCreatedBy()
            ]);
            $id = (int)$this->pdo->lastInsertId();
            return new BgGlossary(
                $id,
                $glossary->getProjectId(),
                $glossary->getTermKey(),
                $glossary->getTermName(),
                $glossary->getTermDescription(),
                $glossary->getCreatedBy(),
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s')
            );
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE bg_glossary
                SET term_key = :term_key, term_name = :term_name, term_description = :term_description
                WHERE id = :id
            ");
            $stmt->execute([
                'term_key' => $glossary->getTermKey(),
                'term_name' => $glossary->getTermName(),
                'term_description' => $glossary->getTermDescription(),
                'id' => $glossary->getId()
            ]);
            return $glossary;
        }
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM bg_glossary WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    private function mapRowToEntity(array $row): BgGlossary
    {
        return new BgGlossary(
            (int)$row['id'],
            (int)$row['project_id'],
            $row['term_key'],
            $row['term_name'],
            $row['term_description'],
            (int)$row['created_by'],
            $row['created_at'],
            $row['updated_at']
        );
    }
}
