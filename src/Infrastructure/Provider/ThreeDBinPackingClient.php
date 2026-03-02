<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use App\Domain\Entity\PackagingBox;
use App\Domain\ValueObject\PackingRequest;
use App\Infrastructure\Provider\Model\PackResult;

interface ThreeDBinPackingClient
{
    /**
     * @param list<PackagingBox> $boxes
     */
    public function pack(PackingRequest $request, array $boxes): PackResult;
}
