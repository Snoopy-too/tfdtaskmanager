<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\BgTemplate;
use App\Domain\Entities\BgComponentType;
use App\Domain\Entities\BgTemplateLayer;
use App\Domain\Repositories\BgTemplateRepositoryInterface;
use App\Domain\Repositories\BgTemplateLayerRepositoryInterface;
use App\Domain\Repositories\BgComponentTypeRepositoryInterface;
use App\Application\Exceptions\ValidationException;

class BgTemplateService
{
    private BgTemplateRepositoryInterface $templateRepository;
    private BgTemplateLayerRepositoryInterface $layerRepository;
    private BgComponentTypeRepositoryInterface $componentTypeRepository;

    public function __construct(
        BgTemplateRepositoryInterface $templateRepository,
        BgTemplateLayerRepositoryInterface $layerRepository,
        BgComponentTypeRepositoryInterface $componentTypeRepository
    ) {
        $this->templateRepository = $templateRepository;
        $this->layerRepository = $layerRepository;
        $this->componentTypeRepository = $componentTypeRepository;
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

        $this->templateRepository->update($updated);
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
}
