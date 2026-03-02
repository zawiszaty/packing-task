<?php

declare(strict_types=1);

namespace App\Domain\Policy\Packing;

use App\Domain\Entity\PackagingBox;
use App\Domain\ValueObject\PackingRequest;

interface PackingPolicy
{
    /**
     * @param list<PackagingBox> $boxes
     */
    public function pack(PackingRequest $request, array $boxes): ?PackagingBox;

    public function source(): string;

    public function failoverPolicySource(): string;
}
