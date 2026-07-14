<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\BgTemplate;

interface BgTemplateRepositoryInterface
{
    /**
     * @return BgTemplate[]
     */
    public function findByProjectId(int $projectId): array;

    public function findById(int $id): ?BgTemplate;

    public function save(BgTemplate $template): BgTemplate;

    public function update(BgTemplate $template): void;

    public function delete(int $id): void;

    public function updateCanvasJson(int $id, string $canvasJson): void;
}
