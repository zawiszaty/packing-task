<?php

declare(strict_types=1);

namespace App\Infrastructure\Policy;

use App\Domain\Entity\PackagingBox;
use App\Domain\Policy\Packing\PackingPolicy;
use App\Domain\Policy\Packing\ProviderSelection;
use App\Domain\Service\SmallestBoxSelector;
use App\Domain\ValueObject\PackingRequest;

final class ManualPackingPolicy implements PackingPolicy
{
    public function __construct(private readonly SmallestBoxSelector $selector)
    {
    }

    public function pack(PackingRequest $request, array $boxes): ?PackagingBox
    {
        return $this->selector->select($request, $boxes);
    }

    public function source(): string
    {
        return ProviderSelection::MANUAL->value;
    }

    public function failoverPolicySource(): string
    {
        return $this->source();
    }
}
