<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Policy;

use App\Domain\Service\SimpleSmallestBoxSelector;
use App\Domain\ValueObject\PackingRequest;
use App\Infrastructure\CircuitBreaker\Simple\StaticCircuitBreaker;
use App\Infrastructure\Policy\CircuitBreakerPackingPolicyRegistry;
use App\Infrastructure\Policy\ManualPackingPolicy;
use App\Infrastructure\Policy\ProviderPackingPolicy;
use App\Infrastructure\Provider\Stub\StubThreeDBinPackingClient;
use PHPUnit\Framework\TestCase;

final class CircuitBreakerPackingPolicyRegistryTest extends TestCase
{
    public function testItSelectsProviderPolicyWhenCircuitBreakerIsAvailable(): void
    {
        $registry = new CircuitBreakerPackingPolicyRegistry(
            new StaticCircuitBreaker(true),
            new ProviderPackingPolicy(new StubThreeDBinPackingClient(1), new StaticCircuitBreaker(true)),
            new ManualPackingPolicy(new SimpleSmallestBoxSelector()),
        );

        $policy = $registry->resolve(new PackingRequest([]));

        self::assertSame('provider_3dbinpacking', $policy->source());
    }

    public function testItSelectsManualPolicyWhenCircuitBreakerIsUnavailable(): void
    {
        $registry = new CircuitBreakerPackingPolicyRegistry(
            new StaticCircuitBreaker(false),
            new ProviderPackingPolicy(new StubThreeDBinPackingClient(1), new StaticCircuitBreaker(false)),
            new ManualPackingPolicy(new SimpleSmallestBoxSelector()),
        );

        $policy = $registry->resolve(new PackingRequest([]));

        self::assertSame('manual', $policy->source());
    }
}
