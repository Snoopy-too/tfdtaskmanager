<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entities\BgAsset;
use App\Domain\Repositories\BgAssetRepositoryInterface;
use PDO;

class PDOBgAssetRepository implements BgAssetRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByProjectId(int $projectId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bg_assets WHERE project_id = :project_id ORDER BY created_at DESC");
        $stmt->execute(['project_id' => $projectId]);
        $rows = $stmt->fetchAll();
        $assets = [];
        foreach ($rows as $row) {
            $assets[] = $this->mapRowToEntity($row);
        }
        return $assets;
    }

    public function findById(int $id): ?BgAsset
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bg_assets WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return $this->mapRowToEntity($row);
    }

    public function save(BgAsset $asset): BgAsset
    {
        if ($asset->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO bg_assets (project_id, original_filename, stored_filename, mime_type, file_size_bytes, tag, uploaded_by)
                VALUES (:project_id, :original_filename, :stored_filename, :mime_type, :file_size_bytes, :tag, :uploaded_by)
            ");
            $stmt->execute([
                'project_id' => $asset->getProjectId(),
                'original_filename' => $asset->getOriginalFilename(),
                'stored_filename' => $asset->getStoredFilename(),
                'mime_type' => $asset->getMimeType(),
                'file_size_bytes' => $asset->getFileSizeBytes(),
                'tag' => $asset->getTag(),
                'uploaded_by' => $asset->getUploadedBy()
            ]);
            $id = (int)$this->pdo->lastInsertId();
            return new BgAsset(
                $id,
                $asset->getProjectId(),
                $asset->getOriginalFilename(),
                $asset->getStoredFilename(),
                $asset->getMimeType(),
                $asset->getFileSizeBytes(),
                $asset->getTag(),
                $asset->getUploadedBy(),
                date('Y-m-d H:i:s')
            );
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE bg_assets
                SET tag = :tag
                WHERE id = :id
            ");
            $stmt->execute([
                'tag' => $asset->getTag(),
                'id' => $asset->getId()
            ]);
            return $asset;
        }
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM bg_assets WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function findByTag(int $projectId, string $tag): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bg_assets WHERE project_id = :project_id AND tag = :tag ORDER BY created_at DESC");
        $stmt->execute([
            'project_id' => $projectId,
            'tag' => $tag
        ]);
        $rows = $stmt->fetchAll();
        $assets = [];
        foreach ($rows as $row) {
            $assets[] = $this->mapRowToEntity($row);
        }
        return $assets;
    }

    private function mapRowToEntity(array $row): BgAsset
    {
        return new BgAsset(
            (int)$row['id'],
            (int)$row['project_id'],
            $row['original_filename'],
            $row['stored_filename'],
            $row['mime_type'],
            (int)$row['file_size_bytes'],
            $row['tag'],
            (int)$row['uploaded_by'],
            $row['created_at']
        );
    }
}
