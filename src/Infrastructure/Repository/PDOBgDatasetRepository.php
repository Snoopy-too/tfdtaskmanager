<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entities\BgDataset;
use App\Domain\Repositories\BgDatasetRepositoryInterface;
use PDO;

class PDOBgDatasetRepository implements BgDatasetRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByProjectId(int $projectId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bg_datasets WHERE project_id = :project_id ORDER BY created_at DESC");
        $stmt->execute(['project_id' => $projectId]);
        $rows = $stmt->fetchAll();
        $datasets = [];
        foreach ($rows as $row) {
            $datasets[] = $this->mapRowToEntity($row);
        }
        return $datasets;
    }

    public function findById(int $id): ?BgDataset
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bg_datasets WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return $this->mapRowToEntity($row);
    }

    public function save(BgDataset $dataset): BgDataset
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO bg_datasets (project_id, name, column_map, row_data, created_by)
            VALUES (:project_id, :name, :column_map, :row_data, :created_by)
        ");
        $stmt->execute([
            'project_id' => $dataset->getProjectId(),
            'name' => $dataset->getName(),
            'column_map' => json_encode($dataset->getColumnMap()),
            'row_data' => json_encode($dataset->getRowData()),
            'created_by' => $dataset->getCreatedBy()
        ]);
        $id = (int)$this->pdo->lastInsertId();
        return new BgDataset(
            $id,
            $dataset->getProjectId(),
            $dataset->getName(),
            $dataset->getColumnMap(),
            $dataset->getRowData(),
            $dataset->getCreatedBy(),
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        );
    }

    public function update(BgDataset $dataset): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE bg_datasets
            SET name = :name, column_map = :column_map, row_data = :row_data
            WHERE id = :id
        ");
        $stmt->execute([
            'name' => $dataset->getName(),
            'column_map' => json_encode($dataset->getColumnMap()),
            'row_data' => json_encode($dataset->getRowData()),
            'id' => $dataset->getId()
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM bg_datasets WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    private function mapRowToEntity(array $row): BgDataset
    {
        return new BgDataset(
            (int)$row['id'],
            (int)$row['project_id'],
            $row['name'],
            json_decode($row['column_map'], true) ?: [],
            json_decode($row['row_data'], true) ?: [],
            (int)$row['created_by'],
            $row['created_at'],
            $row['updated_at']
        );
    }
}
