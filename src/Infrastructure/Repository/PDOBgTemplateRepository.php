<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entities\BgTemplate;
use App\Domain\Repositories\BgTemplateRepositoryInterface;
use PDO;

class PDOBgTemplateRepository implements BgTemplateRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        // ponytail: auto-migrate row_filter column if not present
        try {
            $cols = $this->pdo->query("SHOW COLUMNS FROM bg_templates LIKE 'row_filter'")->fetchAll();
            if (empty($cols)) {
                $this->pdo->exec("ALTER TABLE bg_templates ADD COLUMN row_filter VARCHAR(255) DEFAULT NULL AFTER dataset_id");
            }
        } catch (\Throwable $e) {
            // Ignore if already created or read-only
        }
    }

    public function findByProjectId(int $projectId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bg_templates WHERE project_id = :project_id ORDER BY created_at DESC");
        $stmt->execute(['project_id' => $projectId]);
        $rows = $stmt->fetchAll();
        $templates = [];
        foreach ($rows as $row) {
            $templates[] = $this->mapRowToEntity($row);
        }
        return $templates;
    }

    public function findById(int $id): ?BgTemplate
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bg_templates WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return $this->mapRowToEntity($row);
    }

    public function save(BgTemplate $template): BgTemplate
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO bg_templates (project_id, component_type_id, name, canvas_width_px, canvas_height_px, bleed_mm, safe_margin_mm, dataset_id, row_filter, created_by)
            VALUES (:project_id, :component_type_id, :name, :canvas_width_px, :canvas_height_px, :bleed_mm, :safe_margin_mm, :dataset_id, :row_filter, :created_by)
        ");
        $stmt->execute([
            'project_id' => $template->getProjectId(),
            'component_type_id' => $template->getComponentTypeId(),
            'name' => $template->getName(),
            'canvas_width_px' => $template->getCanvasWidthPx(),
            'canvas_height_px' => $template->getCanvasHeightPx(),
            'bleed_mm' => $template->getBleedMm(),
            'safe_margin_mm' => $template->getSafeMarginMm(),
            'dataset_id' => $template->getDatasetId(),
            'row_filter' => $template->getRowFilter(),
            'created_by' => $template->getCreatedBy()
        ]);
        $id = (int)$this->pdo->lastInsertId();
        
        $saved = new BgTemplate(
            $id,
            $template->getProjectId(),
            $template->getComponentTypeId(),
            $template->getName(),
            $template->getCanvasWidthPx(),
            $template->getCanvasHeightPx(),
            $template->getBleedMm(),
            $template->getSafeMarginMm(),
            $template->getDatasetId(),
            $template->getCreatedBy(),
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        );
        $saved->setRowFilter($template->getRowFilter());
        if ($template->getCanvasJson() !== null) {
            $this->updateCanvasJson($id, $template->getCanvasJson());
            $saved->setCanvasJson($template->getCanvasJson());
        }
        return $saved;
    }

    public function update(BgTemplate $template): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE bg_templates
            SET name = :name, dataset_id = :dataset_id, row_filter = :row_filter, bleed_mm = :bleed_mm, safe_margin_mm = :safe_margin_mm
            WHERE id = :id
        ");
        $stmt->execute([
            'name' => $template->getName(),
            'dataset_id' => $template->getDatasetId(),
            'row_filter' => $template->getRowFilter(),
            'bleed_mm' => $template->getBleedMm(),
            'safe_margin_mm' => $template->getSafeMarginMm(),
            'id' => $template->getId()
        ]);
    }

    public function updateRowFilter(int $id, ?string $rowFilter): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE bg_templates
            SET row_filter = :row_filter
            WHERE id = :id
        ");
        $stmt->execute([
            'row_filter' => $rowFilter,
            'id' => $id
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM bg_templates WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function updateCanvasJson(int $id, string $canvasJson): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE bg_templates
            SET canvas_json = :canvas_json
            WHERE id = :id
        ");
        $stmt->execute([
            'canvas_json' => $canvasJson,
            'id' => $id
        ]);
    }

    public function updateLock(int $id, ?int $userId, ?string $lockedAt): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE bg_templates
            SET locked_by_user_id = :locked_by_user_id, locked_at = :locked_at
            WHERE id = :id
        ");
        $stmt->execute([
            'locked_by_user_id' => $userId,
            'locked_at' => $lockedAt,
            'id' => $id
        ]);
    }

    private function mapRowToEntity(array $row): BgTemplate
    {
        $template = new BgTemplate(
            (int)$row['id'],
            (int)$row['project_id'],
            (int)$row['component_type_id'],
            $row['name'],
            (int)$row['canvas_width_px'],
            (int)$row['canvas_height_px'],
            (float)$row['bleed_mm'],
            (float)$row['safe_margin_mm'],
            $row['dataset_id'] !== null ? (int)$row['dataset_id'] : null,
            (int)$row['created_by'],
            $row['created_at'],
            $row['updated_at']
        );
        if (isset($row['row_filter'])) {
            $template->setRowFilter($row['row_filter']);
        }
        if ($row['canvas_json'] !== null) {
            $template->setCanvasJson($row['canvas_json']);
        }
        if (isset($row['locked_by_user_id']) && $row['locked_by_user_id'] !== null) {
            $template->setLockedByUserId((int)$row['locked_by_user_id']);
        }
        if (isset($row['locked_at']) && $row['locked_at'] !== null) {
            $template->setLockedAt($row['locked_at']);
        }
        return $template;
    }
}
