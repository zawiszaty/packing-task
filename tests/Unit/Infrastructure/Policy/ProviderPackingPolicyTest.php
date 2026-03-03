<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Policy;

use App\Domain\Entity\PackagingBox;
use App\Domain\ValueObject\PackingRequest;
use App\Infrastructure\Policy\ProviderPackingPolicy;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fake\Infrastructure\CircuitBreaker\SpyCircuitBreaker;
use Tests\Support\Fake\Infrastructure\Provider\ConfigurableThreeDBinPackingClient;

final class ProviderPackingPolicyTest extends TestCase
{
    public function testItReturnsMatchingBoxAndMarksCircuitBreakerSuccess(): void
    {
        $circuitBreaker = new SpyCircuitBreaker();
        $providerPolicy = new ProviderPackingPolicy(
            providerClient: new ConfigurableThreeDBinPackingClient(selectedBoxId: 2),
            circuitBreaker: $circuitBreaker,
        );

        $selected = $providerPolicy->pack(request: new PackingRequest(products: []), boxes: [
            new PackagingBox(id: 1, width: 1.0, height: 1.0, length: 1.0, maxWeight: 1.0),
            new PackagingBox(id: 2, width: 2.0, height: 2.0, length: 2.0, maxWeight: 2.0),
        ]);

        self::assertNotNull($selected);
        self::assertSame(2, $selected->id);
        self::assertSame(1, $circuitBreaker->successCalls);
        self::assertSame(0, $circuitBreaker->failureCalls);
    }

    public function testItMarksFailureAndRethrowsOnProviderException(): void
    {
        $circuitBreaker = new SpyCircuitBreaker();
        $providerPolicy = new ProviderPackingPolicy(
            providerClient: new ConfigurableThreeDBinPackingClient(throwMessage: 'provider failed'),
            circuitBreaker: $circuitBreaker,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('provider failed');

        try {
            $providerPolicy->pack(request: new PackingRequest(products: []), boxes: []);
        } finally {
            self::assertSame(0, $circuitBreaker->successCalls);
            self::assertSame(1, $circuitBreaker->failureCalls);
        }
    }

    public function testItReturnsNullWhenProviderReturnsUnknownBoxId(): void
    {
        $circuitBreaker = new SpyCircuitBreaker();
        $providerPolicy = new ProviderPackingPolicy(
            providerClient: new ConfigurableThreeDBinPackingClient(selectedBoxId: 999),
            circuitBreaker: $circuitBreaker,
        );

        $selected = $providerPolicy->pack(
            request: new PackingRequest(products: []),
            boxes: [new PackagingBox(id: 1, width: 1.0, height: 1.0, length: 1.0, maxWeight: 1.0)],
        );

        self::assertNull($selected);
        self::assertSame(1, $circuitBreaker->successCalls);
    }

    public function testItReturnsNullWhenProviderReturnsNoBox(): void
    {
        $circuitBreaker = new SpyCircuitBreaker();
        $providerPolicy = new ProviderPackingPolicy(
            providerClient: new ConfigurableThreeDBinPackingClient(selectedBoxId: null),
            circuitBreaker: $circuitBreaker,
        );

        $selected = $providerPolicy->pack(
            request: new PackingRequest(products: []),
            boxes: [new PackagingBox(id: 1, width: 1.0, height: 1.0, length: 1.0, maxWeight: 1.0)],
        );

        self::assertNull($selected);
        self::assertSame(1, $circuitBreaker->successCalls);
        self::assertSame(0, $circuitBreaker->failureCalls);
    }
}
