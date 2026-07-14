<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\BgDataset;

interface BgDatasetRepositoryInterface
{
    /**
     * @return BgDataset[]
     */
    public function findByProjectId(int $projectId): array;

    public function findById(int $id): ?BgDataset;

    public function save(BgDataset $dataset): BgDataset;

    public function update(BgDataset $dataset): void;

    public function delete(int $id): void;
}
