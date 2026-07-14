<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\BgTemplateLayer;

interface BgTemplateLayerRepositoryInterface
{
    /**
     * @return BgTemplateLayer[]
     */
    public function findByTemplateId(int $templateId): array;

    public function findById(int $id): ?BgTemplateLayer;

    public function save(BgTemplateLayer $layer): BgTemplateLayer;

    public function update(BgTemplateLayer $layer): void;

    public function delete(int $id): void;

    /**
     * @param array<int, int> $layerZIndexMap Array mapping layer ID to new z-index
     */
    public function reorder(array $layerZIndexMap): void;
}
