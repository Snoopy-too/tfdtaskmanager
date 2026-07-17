<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\BgRulebook;

interface BgRulebookRepositoryInterface
{
    /**
     * @return BgRulebook[]
     */
    public function findByProjectId(int $projectId): array;

    public function findById(int $id): ?BgRulebook;

    public function save(BgRulebook $rulebook): BgRulebook;

    public function delete(int $id): void;
}
