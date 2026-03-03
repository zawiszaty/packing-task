<?php

declare(strict_types=1);

namespace Tests\Support\Fake\Domain\Policy;

use App\Domain\Policy\Packing\PackingPolicy;
use App\Domain\Policy\Packing\PackingPolicyRegistry;
use App\Domain\ValueObject\PackingRequest;
use RuntimeException;

final class ConfigurablePackingPolicyRegistry implements PackingPolicyRegistry
{
    /**
     * @param array<string, PackingPolicy> $policiesBySource
     */
    public function __construct(
        private readonly string $resolvedSource,
        private readonly array $policiesBySource,
    ) {
    }

    public function resolve(PackingRequest $request): PackingPolicy
    {
        $policy = $this->policiesBySource[$this->resolvedSource] ?? null;
        if ($policy === null) {
            throw new RuntimeException(sprintf('Missing resolved policy "%s".', $this->resolvedSource));
        }

        return $policy;
    }

    public function bySource(string $source): ?PackingPolicy
    {
        return $this->policiesBySource[$source] ?? null;
    }
}
