<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\BgAsset;

interface BgAssetRepositoryInterface
{
    /**
     * @return BgAsset[]
     */
    public function findByProjectId(?int $projectId, bool $includeGlobal = true): array;

    public function findById(int $id): ?BgAsset;

    public function save(BgAsset $asset): BgAsset;

    public function delete(int $id): void;

    /**
     * @return BgAsset[]
     */
    public function findByTag(?int $projectId, string $tag): array;
}
