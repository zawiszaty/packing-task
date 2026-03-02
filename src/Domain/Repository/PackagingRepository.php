<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\PackagingBox;

interface PackagingRepository
{
    /**
     * @return list<PackagingBox>
     */
    public function findAll(): array;
}
