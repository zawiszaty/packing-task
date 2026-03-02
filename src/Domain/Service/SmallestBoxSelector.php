<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\PackagingBox;
use App\Domain\ValueObject\PackingRequest;

interface SmallestBoxSelector
{
    /**
     * @param list<PackagingBox> $boxes
     */
    public function select(PackingRequest $request, array $boxes): ?PackagingBox;
}
