<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entities\BgTemplateLayer;
use App\Domain\Repositories\BgTemplateLayerRepositoryInterface;
use PDO;

class PDOBgTemplateLayerRepository implements BgTemplateLayerRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByTemplateId(int $templateId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bg_template_layers WHERE template_id = :template_id ORDER BY z_index ASC");
        $stmt->execute(['template_id' => $templateId]);
        $rows = $stmt->fetchAll();
        $layers = [];
        foreach ($rows as $row) {
            $layers[] = $this->mapRowToEntity($row);
        }
        return $layers;
    }

    public function findById(int $id): ?BgTemplateLayer
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bg_template_layers WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return $this->mapRowToEntity($row);
    }

    public function save(BgTemplateLayer $layer): BgTemplateLayer
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO bg_template_layers (template_id, name, layer_type, z_index, x_pos, y_pos, width, height, rotation, opacity, properties, variable_binding, is_visible, is_locked)
            VALUES (:template_id, :name, :layer_type, :z_index, :x_pos, :y_pos, :width, :height, :rotation, :opacity, :properties, :variable_binding, :is_visible, :is_locked)
        ");
        $stmt->execute([
            'template_id' => $layer->getTemplateId(),
            'name' => $layer->getName(),
            'layer_type' => $layer->getLayerType(),
            'z_index' => $layer->getZIndex(),
            'x_pos' => $layer->getXPos(),
            'y_pos' => $layer->getYPos(),
            'width' => $layer->getWidth(),
            'height' => $layer->getHeight(),
            'rotation' => $layer->getRotation(),
            'opacity' => $layer->getOpacity(),
            'properties' => json_encode($layer->getProperties()),
            'variable_binding' => $layer->getVariableBinding(),
            'is_visible' => $layer->isVisible() ? 1 : 0,
            'is_locked' => $layer->isLocked() ? 1 : 0
        ]);
        $id = (int)$this->pdo->lastInsertId();
        return new BgTemplateLayer(
            $id,
            $layer->getTemplateId(),
            $layer->getName(),
            $layer->getLayerType(),
            $layer->getZIndex(),
            $layer->getXPos(),
            $layer->getYPos(),
            $layer->getWidth(),
            $layer->getHeight(),
            $layer->getRotation(),
            $layer->getOpacity(),
            $layer->getProperties(),
            $layer->getVariableBinding(),
            $layer->isVisible(),
            $layer->isLocked()
        );
    }

    public function update(BgTemplateLayer $layer): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE bg_template_layers
            SET name = :name, z_index = :z_index, x_pos = :x_pos, y_pos = :y_pos, width = :width, height = :height, rotation = :rotation, opacity = :opacity, properties = :properties, variable_binding = :variable_binding, is_visible = :is_visible, is_locked = :is_locked
            WHERE id = :id
        ");
        $stmt->execute([
            'name' => $layer->getName(),
            'z_index' => $layer->getZIndex(),
            'x_pos' => $layer->getXPos(),
            'y_pos' => $layer->getYPos(),
            'width' => $layer->getWidth(),
            'height' => $layer->getHeight(),
            'rotation' => $layer->getRotation(),
            'opacity' => $layer->getOpacity(),
            'properties' => json_encode($layer->getProperties()),
            'variable_binding' => $layer->getVariableBinding(),
            'is_visible' => $layer->isVisible() ? 1 : 0,
            'is_locked' => $layer->isLocked() ? 1 : 0,
            'id' => $layer->getId()
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM bg_template_layers WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function reorder(array $layerZIndexMap): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("UPDATE bg_template_layers SET z_index = :z_index WHERE id = :id");
            foreach ($layerZIndexMap as $id => $zIndex) {
                $stmt->execute([
                    'z_index' => $zIndex,
                    'id' => $id
                ]);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function mapRowToEntity(array $row): BgTemplateLayer
    {
        return new BgTemplateLayer(
            (int)$row['id'],
            (int)$row['template_id'],
            $row['name'],
            $row['layer_type'],
            (int)$row['z_index'],
            (float)$row['x_pos'],
            (float)$row['y_pos'],
            (float)$row['width'],
            (float)$row['height'],
            (float)$row['rotation'],
            (float)$row['opacity'],
            json_decode($row['properties'], true) ?: [],
            $row['variable_binding'],
            (bool)$row['is_visible'],
            (bool)$row['is_locked']
        );
    }
}
