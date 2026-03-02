<?php

declare(strict_types=1);

namespace App\Domain\Policy\Packing;

use App\Domain\ValueObject\PackingRequest;

interface PackingPolicyRegistry
{
    public function resolve(PackingRequest $request): PackingPolicy;

    public function bySource(string $source): ?PackingPolicy;
}
