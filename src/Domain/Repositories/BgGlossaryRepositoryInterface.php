<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\BgGlossary;

interface BgGlossaryRepositoryInterface
{
    /**
     * @return BgGlossary[]
     */
    public function findByProjectId(int $projectId): array;

    public function findById(int $id): ?BgGlossary;

    public function findByTermKey(int $projectId, string $termKey): ?BgGlossary;

    public function save(BgGlossary $glossary): BgGlossary;

    public function delete(int $id): void;
}
