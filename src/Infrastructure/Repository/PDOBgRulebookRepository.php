<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entities\BgRulebook;
use App\Domain\Repositories\BgRulebookRepositoryInterface;
use PDO;

class PDOBgRulebookRepository implements BgRulebookRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByProjectId(int $projectId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bg_rulebooks WHERE project_id = :project_id ORDER BY created_at DESC");
        $stmt->execute(['project_id' => $projectId]);
        $rows = $stmt->fetchAll();
        $rulebooks = [];
        foreach ($rows as $row) {
            $rulebooks[] = $this->mapRowToEntity($row);
        }
        return $rulebooks;
    }

    public function findById(int $id): ?BgRulebook
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bg_rulebooks WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return $this->mapRowToEntity($row);
    }

    public function save(BgRulebook $rulebook): BgRulebook
    {
        $contentJson = json_encode($rulebook->getContent(), JSON_UNESCAPED_UNICODE);
        if ($rulebook->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO bg_rulebooks (project_id, name, content, created_by)
                VALUES (:project_id, :name, :content, :created_by)
            ");
            $stmt->execute([
                'project_id' => $rulebook->getProjectId(),
                'name' => $rulebook->getName(),
                'content' => $contentJson,
                'created_by' => $rulebook->getCreatedBy()
            ]);
            $id = (int)$this->pdo->lastInsertId();
            return new BgRulebook(
                $id,
                $rulebook->getProjectId(),
                $rulebook->getName(),
                $rulebook->getContent(),
                $rulebook->getCreatedBy(),
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s')
            );
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE bg_rulebooks
                SET name = :name, content = :content
                WHERE id = :id
            ");
            $stmt->execute([
                'name' => $rulebook->getName(),
                'content' => $contentJson,
                'id' => $rulebook->getId()
            ]);
            return $rulebook;
        }
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM bg_rulebooks WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function updateLock(int $id, ?int $userId, ?string $lockedAt): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE bg_rulebooks
            SET locked_by_user_id = :locked_by_user_id, locked_at = :locked_at
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $id,
            'locked_by_user_id' => $userId,
            'locked_at' => $lockedAt
        ]);
    }

    private function mapRowToEntity(array $row): BgRulebook
    {
        $content = json_decode($row['content'], true) ?: [];
        $lockedByUserId = isset($row['locked_by_user_id']) && $row['locked_by_user_id'] !== null ? (int)$row['locked_by_user_id'] : null;
        $lockedAt = $row['locked_at'] ?? null;
        return new BgRulebook(
            (int)$row['id'],
            (int)$row['project_id'],
            $row['name'],
            $content,
            (int)$row['created_by'],
            $row['created_at'],
            $row['updated_at'],
            $lockedByUserId,
            $lockedAt
        );
    }
}
