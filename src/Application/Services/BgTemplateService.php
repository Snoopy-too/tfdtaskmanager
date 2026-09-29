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
    private BgBoxDielineService $boxDielineService;

    public function __construct(
        BgTemplateRepositoryInterface $templateRepository,
        BgTemplateLayerRepositoryInterface $layerRepository,
        BgComponentTypeRepositoryInterface $componentTypeRepository,
        ?BgRulebookRepositoryInterface $rulebookRepository = null,
        ?BgBoxDielineService $boxDielineService = null
    ) {
        $this->templateRepository = $templateRepository;
        $this->layerRepository = $layerRepository;
        $this->componentTypeRepository = $componentTypeRepository;
        $this->rulebookRepository = $rulebookRepository;
        $this->boxDielineService = $boxDielineService ?? new BgBoxDielineService();
    }

    public function getBoxDielineService(): BgBoxDielineService
    {
        return $this->boxDielineService;
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
        ?float $customHeightMm = null,
        string $orientation = 'portrait',
        ?array $boxParams = null
    ): BgTemplate {
        $name = trim($name);
        if (empty($name)) {
            throw new ValidationException("Template name is required.");
        }

        $compType = $this->componentTypeRepository->findById($componentTypeId);
        if (!$compType) {
            throw new ValidationException("Invalid component type.");
        }

        if (str_contains($compType->getName(), 'Board Game Box') && is_array($boxParams)) {
            return $this->createBoxTemplate(
                $projectId,
                $componentTypeId,
                $name,
                $bleedMm,
                $safeMarginMm,
                $datasetId,
                $createdByUserId,
                $boxParams
            );
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

        // Apply orientation
        if ($orientation === 'landscape' && $widthMm < $heightMm) {
            $temp = $widthMm;
            $widthMm = $heightMm;
            $heightMm = $temp;
        } elseif ($orientation === 'portrait' && $widthMm > $heightMm) {
            $temp = $widthMm;
            $widthMm = $heightMm;
            $heightMm = $temp;
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

    /**
     * Creates an unfolded flat Board Game Box template (or both Base + Lid templates if 'pair' is selected)
     * with pre-populated cut & fold die-line layers.
     *
     * @param array<string, mixed> $boxParams
     */
    public function createBoxTemplate(
        int $projectId,
        int $componentTypeId,
        string $name,
        float $bleedMm,
        float $safeMarginMm,
        ?int $datasetId,
        int $createdByUserId,
        array $boxParams
    ): BgTemplate {
        $config = $this->boxDielineService->validateConfig($boxParams);
        $part = $config['boxPart'];

        if ($part === BgBoxDielineService::PART_PAIR && $config['boxType'] !== BgBoxDielineService::TYPE_TUCK_TOP_BOX) {
            $baseName = $name . ' (Bottom Base)';
            $lidName  = $name . ' (Top Lid)';

            $baseTemplate = $this->persistSingleBoxPiece(
                $projectId,
                $componentTypeId,
                $baseName,
                $bleedMm,
                $safeMarginMm,
                $datasetId,
                $createdByUserId,
                $config,
                BgBoxDielineService::PART_BASE
            );

            $lidConfig = $config;
            $lidConfig['companionTemplateId'] = (int)$baseTemplate->getId();
            $lidConfig['companionTemplateName'] = $baseName;

            $lidTemplate = $this->persistSingleBoxPiece(
                $projectId,
                $componentTypeId,
                $lidName,
                $bleedMm,
                $safeMarginMm,
                $datasetId,
                $createdByUserId,
                $lidConfig,
                BgBoxDielineService::PART_LID
            );

            // Update Base template's canvas payload with the newly created Lid template ID & name
            $baseConfigWithLink = $config;
            $baseConfigWithLink['companionTemplateId'] = (int)$lidTemplate->getId();
            $baseConfigWithLink['companionTemplateName'] = $lidName;
            $baseGeom = $this->boxDielineService->calculateGeometry($baseConfigWithLink, BgBoxDielineService::PART_BASE);
            $basePayload = $this->boxDielineService->buildFabricCanvasPayload($baseGeom);
            $this->saveCanvas((int)$baseTemplate->getId(), $basePayload['canvasJson'], $basePayload['layersData']);

            return $this->templateRepository->findById((int)$baseTemplate->getId()) ?? $baseTemplate;
        }

        $singlePiece = ($part === BgBoxDielineService::PART_LID)
            ? BgBoxDielineService::PART_LID
            : BgBoxDielineService::PART_BASE;

        return $this->persistSingleBoxPiece(
            $projectId,
            $componentTypeId,
            $name,
            $bleedMm,
            $safeMarginMm,
            $datasetId,
            $createdByUserId,
            $config,
            $singlePiece
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function persistSingleBoxPiece(
        int $projectId,
        int $componentTypeId,
        string $name,
        float $bleedMm,
        float $safeMarginMm,
        ?int $datasetId,
        int $createdByUserId,
        array $config,
        string $piece
    ): BgTemplate {
        $geom = $this->boxDielineService->calculateGeometry($config, $piece);
        $payload = $this->boxDielineService->buildFabricCanvasPayload($geom);

        $template = new BgTemplate(
            null,
            $projectId,
            $componentTypeId,
            $name,
            (int)$geom['canvasWidthPx'],
            (int)$geom['canvasHeightPx'],
            $bleedMm,
            $safeMarginMm,
            $datasetId,
            $createdByUserId
        );

        $savedTemplate = $this->templateRepository->save($template);
        $this->saveCanvas((int)$savedTemplate->getId(), $payload['canvasJson'], $payload['layersData']);

        return $this->templateRepository->findById((int)$savedTemplate->getId()) ?? $savedTemplate;
    }

    public function updateTemplateDimensions(int $id, int $widthPx, int $heightPx): BgTemplate
    {
        if ($widthPx <= 0 || $heightPx <= 0) {
            throw new ValidationException("Dimensions must be greater than 0.");
        }

        $template = $this->templateRepository->findById($id);
        if (!$template) {
            throw new ValidationException("Template not found.");
        }

        $updated = new BgTemplate(
            $id,
            $template->getProjectId(),
            $template->getComponentTypeId(),
            $template->getName(),
            $widthPx,
            $heightPx,
            $template->getBleedMm(),
            $template->getSafeMarginMm(),
            $template->getDatasetId(),
            $template->getCreatedBy(),
            $template->getCreatedAt()
        );
        $updated->setRowFilter($template->getRowFilter());
        if ($template->getCanvasJson() !== null) {
            $updated->setCanvasJson($template->getCanvasJson());
        }

        $this->templateRepository->update($updated);
        return $updated;
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
        $updated->setRowFilter($template->getRowFilter());
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
