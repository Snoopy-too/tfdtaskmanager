<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\BgTemplate;
use App\Domain\Entities\BgComponentType;
use App\Domain\Entities\BgTemplateLayer;
use App\Domain\Repositories\BgTemplateRepositoryInterface;
use App\Domain\Repositories\BgTemplateLayerRepositoryInterface;
use App\Domain\Repositories\BgComponentTypeRepositoryInterface;
use App\Domain\Repositories\BgRulebookRepositoryInterface;
use App\Domain\Entities\BgRulebook;
use App\Application\Exceptions\ValidationException;

class BgTemplateService
{
    private BgTemplateRepositoryInterface $templateRepository;
    private BgTemplateLayerRepositoryInterface $layerRepository;
    private BgComponentTypeRepositoryInterface $componentTypeRepository;
    private ?BgRulebookRepositoryInterface $rulebookRepository;

    public function __construct(
        BgTemplateRepositoryInterface $templateRepository,
        BgTemplateLayerRepositoryInterface $layerRepository,
        BgComponentTypeRepositoryInterface $componentTypeRepository,
        ?BgRulebookRepositoryInterface $rulebookRepository = null
    ) {
        $this->templateRepository = $templateRepository;
        $this->layerRepository = $layerRepository;
        $this->componentTypeRepository = $componentTypeRepository;
        $this->rulebookRepository = $rulebookRepository;
    }

    public function getTemplatesByProject(int $projectId): array
    {
        return $this->templateRepository->findByProjectId($projectId);
    }

    public function getTemplateById(int $id): ?BgTemplate
    {
        return $this->templateRepository->findById($id);
    }

    public function getComponentTypes(): array
    {
        return $this->componentTypeRepository->findAll();
    }

    public function getComponentTypeById(int $id): ?BgComponentType
    {
        return $this->componentTypeRepository->findById($id);
    }

    public function createTemplate(
        int $projectId,
        int $componentTypeId,
        string $name,
        float $bleedMm,
        float $safeMarginMm,
        ?int $datasetId,
        int $createdByUserId,
        ?float $customWidthMm = null,
        ?float $customHeightMm = null
    ): BgTemplate {
        $name = trim($name);
        if (empty($name)) {
            throw new ValidationException("Template name is required.");
        }

        $compType = $this->componentTypeRepository->findById($componentTypeId);
        if (!$compType) {
            throw new ValidationException("Invalid component type.");
        }

        $widthMm = $compType->getWidthMm();
        $heightMm = $compType->getHeightMm();

        if ($compType->getName() === 'Custom') {
            if (!$customWidthMm || !$customHeightMm || $customWidthMm <= 0 || $customHeightMm <= 0) {
                throw new ValidationException("Custom dimensions must be greater than 0.");
            }
            $widthMm = $customWidthMm;
            $heightMm = $customHeightMm;
        }

        // Calculate pixel dimensions at 300 DPI
        $widthPx = BgTemplate::mmToPx($widthMm, 300);
        $heightPx = BgTemplate::mmToPx($heightMm, 300);

        $template = new BgTemplate(
            null,
            $projectId,
            $componentTypeId,
            $name,
            $widthPx,
            $heightPx,
            $bleedMm,
            $safeMarginMm,
            $datasetId,
            $createdByUserId
        );

        return $this->templateRepository->save($template);
    }

    public function updateTemplate(
        int $id,
        string $name,
        float $bleedMm,
        float $safeMarginMm,
        ?int $datasetId
    ): BgTemplate {
        $name = trim($name);
        if (empty($name)) {
            throw new ValidationException("Template name is required.");
        }

        $template = $this->templateRepository->findById($id);
        if (!$template) {
            throw new ValidationException("Template not found.");
        }

        $oldName = $template->getName();

        $updated = new BgTemplate(
            $id,
            $template->getProjectId(),
            $template->getComponentTypeId(),
            $name,
            $template->getCanvasWidthPx(),
            $template->getCanvasHeightPx(),
            $bleedMm,
            $safeMarginMm,
            $datasetId,
            $template->getCreatedBy(),
            $template->getCreatedAt()
        );
        if ($template->getCanvasJson() !== null) {
            $updated->setCanvasJson($template->getCanvasJson());
        }

        $this->templateRepository->update($updated);

        // Sync rulebooks if the template name has changed
        if ($oldName !== $name && $this->rulebookRepository !== null) {
            $rulebooks = $this->rulebookRepository->findByProjectId($template->getProjectId());
            foreach ($rulebooks as $rulebook) {
                $contentJson = json_encode($rulebook->getContent(), JSON_UNESCAPED_UNICODE);
                if (str_contains($contentJson, $oldName)) {
                    $newContentJson = str_replace($oldName, $name, $contentJson);
                    $newContent = json_decode($newContentJson, true) ?: [];
                    $updatedRulebook = new BgRulebook(
                        $rulebook->getId(),
                        $rulebook->getProjectId(),
                        $rulebook->getName(),
                        $newContent,
                        $rulebook->getCreatedBy(),
                        $rulebook->getCreatedAt(),
                        date('Y-m-d H:i:s'),
                        $rulebook->getLockedByUserId(),
                        $rulebook->getLockedAt()
                    );
                    $this->rulebookRepository->save($updatedRulebook);
                }
            }
        }

        return $updated;
    }

    public function deleteTemplate(int $id): void
    {
        $this->templateRepository->delete($id);
    }

    /**
     * Auto-saves the Canvas JSON state and syncs the simplified layer metadata
     * for easy querying and layer management operations.
     */
    public function saveCanvas(int $id, string $canvasJson, array $layersData): void
    {
        $template = $this->templateRepository->findById($id);
        if (!$template) {
            throw new ValidationException("Template not found.");
        }

        // 1. Save Canvas JSON
        $this->templateRepository->updateCanvasJson($id, $canvasJson);

        // 2. Clear old layers and sync the new ones
        $existingLayers = $this->layerRepository->findByTemplateId($id);
        foreach ($existingLayers as $oldLayer) {
            $this->layerRepository->delete((int)$oldLayer->getId());
        }

        // 3. Save new layer metadata
        foreach ($layersData as $index => $layer) {
            $properties = $layer['properties'] ?? [];
            $variableBinding = $layer['variable_binding'] ?? null;
            if (empty($variableBinding) && isset($layer['text'])) {
                // Infer variable binding from text if contains {{Var}}
                if (preg_match('/\{\{([a-zA-Z0-9_\-]+)\}\}/', $layer['text'], $matches)) {
                    $variableBinding = $matches[0];
                }
            }

            $newLayer = new BgTemplateLayer(
                null,
                $id,
                $layer['name'] ?? ('Layer ' . ($index + 1)),
                $layer['layer_type'] ?? 'shape',
                (int)($layer['z_index'] ?? $index),
                (float)($layer['x_pos'] ?? 0),
                (float)($layer['y_pos'] ?? 0),
                (float)($layer['width'] ?? 100),
                (float)($layer['height'] ?? 100),
                (float)($layer['rotation'] ?? 0),
                (float)($layer['opacity'] ?? 1),
                $properties,
                $variableBinding,
                (bool)($layer['is_visible'] ?? true),
                (bool)($layer['is_locked'] ?? false)
            );

            $this->layerRepository->save($newLayer);
        }
    }

    public function getTemplateLayers(int $templateId): array
    {
        return $this->layerRepository->findByTemplateId($templateId);
    }

    public function cloneTemplate(int $id, string $newName, int $currentUserId): BgTemplate
    {
        $template = $this->templateRepository->findById($id);
        if (!$template) {
            throw new ValidationException("Template not found.");
        }

        $newName = trim($newName);
        if (empty($newName)) {
            throw new ValidationException("Template name is required.");
        }

        $cloned = new BgTemplate(
            null,
            $template->getProjectId(),
            $template->getComponentTypeId(),
            $newName,
            $template->getCanvasWidthPx(),
            $template->getCanvasHeightPx(),
            $template->getBleedMm(),
            $template->getSafeMarginMm(),
            $template->getDatasetId(),
            $currentUserId
        );
        $cloned->setRowFilter($template->getRowFilter());

        if ($template->getCanvasJson() !== null) {
            $cloned->setCanvasJson($template->getCanvasJson());
        }

        $saved = $this->templateRepository->save($cloned);

        $layers = $this->layerRepository->findByTemplateId($id);
        foreach ($layers as $layer) {
            $clonedLayer = new BgTemplateLayer(
                null,
                $saved->getId(),
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
            $this->layerRepository->save($clonedLayer);
        }

        return $saved;
    }

    public function isTemplateLockedByOther(BgTemplate $template, int $currentUserId): bool
    {
        if ($template->getLockedByUserId() === null) {
            return false;
        }
        if ($template->getLockedByUserId() === $currentUserId) {
            return false;
        }
        $lockedTime = strtotime($template->getLockedAt());
        if ($lockedTime === false) {
            return false;
        }
        return (time() - $lockedTime) < 60; // Lock is valid for 60 seconds
    }

    public function acquireOrRefreshLock(int $templateId, int $userId): bool
    {
        $template = $this->templateRepository->findById($templateId);
        if (!$template) {
            return false;
        }

        if ($this->isTemplateLockedByOther($template, $userId)) {
            return false;
        }

        $this->templateRepository->updateLock($templateId, $userId, date('Y-m-d H:i:s'));
        return true;
    }

    public function releaseLock(int $templateId, int $userId): void
    {
        $template = $this->templateRepository->findById($templateId);
        if ($template && $template->getLockedByUserId() === $userId) {
            $this->templateRepository->updateLock($templateId, null, null);
        }
    }

    public function updateTemplateRowFilter(int $templateId, ?string $rowFilter): void
    {
        $this->templateRepository->updateRowFilter($templateId, $rowFilter);
    }
}
