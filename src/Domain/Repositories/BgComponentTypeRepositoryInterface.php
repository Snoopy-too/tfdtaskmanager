<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\BgComponentType;

interface BgComponentTypeRepositoryInterface
{
    /**
     * @return BgComponentType[]
     */
    public function findAll(): array;

    public function findById(int $id): ?BgComponentType;
}
