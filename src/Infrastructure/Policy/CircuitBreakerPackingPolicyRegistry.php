<?php

declare(strict_types=1);

namespace App\Infrastructure\Policy;

use App\Domain\Policy\Packing\PackingPolicy;
use App\Domain\Policy\Packing\PackingPolicyRegistry;
use App\Domain\ValueObject\PackingRequest;
use App\Infrastructure\CircuitBreaker\CircuitBreaker;

final class CircuitBreakerPackingPolicyRegistry implements PackingPolicyRegistry
{
    public function __construct(
        private readonly CircuitBreaker $circuitBreaker,
        private readonly PackingPolicy $providerPolicy,
        private readonly PackingPolicy $manualPolicy,
    ) {
    }

    public function resolve(PackingRequest $request): PackingPolicy
    {
        if ($this->circuitBreaker->isAvailable('3dbinpacking')) {
            return $this->providerPolicy;
        }

        return $this->manualPolicy;
    }

    public function bySource(string $source): ?PackingPolicy
    {
        if ($source === $this->providerPolicy->source()) {
            return $this->providerPolicy;
        }

        if ($source === $this->manualPolicy->source()) {
            return $this->manualPolicy;
        }

        return null;
    }
}
