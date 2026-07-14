<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entities\BgComponentType;
use App\Domain\Repositories\BgComponentTypeRepositoryInterface;
use PDO;

class PDOBgComponentTypeRepository implements BgComponentTypeRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM bg_component_types ORDER BY id ASC");
        $rows = $stmt->fetchAll();
        $types = [];
        foreach ($rows as $row) {
            $types[] = $this->mapRowToEntity($row);
        }
        return $types;
    }

    public function findById(int $id): ?BgComponentType
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bg_component_types WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return $this->mapRowToEntity($row);
    }

    private function mapRowToEntity(array $row): BgComponentType
    {
        return new BgComponentType(
            (int)$row['id'],
            $row['name'],
            (float)$row['width_mm'],
            (float)$row['height_mm'],
            $row['description']
        );
    }
}
