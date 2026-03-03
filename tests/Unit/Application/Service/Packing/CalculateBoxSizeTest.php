<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Service\Packing;

use App\Application\Service\Packing\CalculateBoxSize;
use App\Domain\Entity\PackagingBox;
use App\Domain\ValueObject\Dimensions;
use App\Domain\ValueObject\PackingRequest;
use App\Domain\ValueObject\ProductToPack;
use App\Domain\ValueObject\Weight;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fake\Domain\Policy\ConfigurablePackingPolicy;
use Tests\Support\Fake\Domain\Policy\ConfigurablePackingPolicyRegistry;
use Tests\Support\Fake\Infrastructure\Logger\InMemoryLogger;

final class CalculateBoxSizeTest extends TestCase
{
    public function testItReturnsResultFromResolvedPolicy(): void
    {
        $registry = new ConfigurablePackingPolicyRegistry(
            resolvedSource: 'provider',
            policiesBySource: [
                'provider' => new ConfigurablePackingPolicy(source: 'provider', failoverSource: 'provider'),
            ],
        );
        $service = new CalculateBoxSize(
            packingPolicyRegistry: $registry,
            logger: new InMemoryLogger(),
        );

        $result = $service->calculate(
            request: $this->request(),
            boxes: [new PackagingBox(id: 1, width: 3.0, height: 3.0, length: 3.0, maxWeight: 10.0)],
            requestHash: 'hash-1',
        );

        self::assertSame('provider', $result->source);
        self::assertNotNull($result->selectedBox);
        self::assertSame(1, $result->selectedBox->id);
    }

    public function testItFallsBackToFailoverPolicyAndLogsError(): void
    {
        $logger = new InMemoryLogger();
        $registry = new ConfigurablePackingPolicyRegistry(
            resolvedSource: 'provider',
            policiesBySource: [
                'provider' => new ConfigurablePackingPolicy(
                    throwMessage: 'provider down',
                    source: 'provider',
                    failoverSource: 'manual',
                ),
                'manual' => new ConfigurablePackingPolicy(source: 'manual', failoverSource: 'manual'),
            ],
        );
        $service = new CalculateBoxSize(
            packingPolicyRegistry: $registry,
            logger: $logger,
        );

        $result = $service->calculate(
            request: $this->request(),
            boxes: [new PackagingBox(id: 2, width: 4.0, height: 4.0, length: 4.0, maxWeight: 10.0)],
            requestHash: 'hash-2',
        );

        self::assertSame('manual', $result->source);
        self::assertNotNull($result->selectedBox);
        self::assertSame(2, $result->selectedBox->id);

        $records = $logger->recordsBy(level: 'error', message: 'packing.policy_failed_using_failover');
        self::assertCount(1, $records);
        self::assertSame('hash-2', $records[0]['context']['requestHash']);
        self::assertSame('provider', $records[0]['context']['policy']);
        self::assertSame('manual', $records[0]['context']['failoverPolicy']);
    }

    public function testItRethrowsOriginalExceptionWhenFailoverPolicyIsMissing(): void
    {
        $registry = new ConfigurablePackingPolicyRegistry(
            resolvedSource: 'provider',
            policiesBySource: [
                'provider' => new ConfigurablePackingPolicy(
                    throwMessage: 'provider down',
                    source: 'provider',
                    failoverSource: 'missing',
                ),
            ],
        );
        $service = new CalculateBoxSize(
            packingPolicyRegistry: $registry,
            logger: new InMemoryLogger(),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('provider down');

        $service->calculate(
            request: $this->request(),
            boxes: [new PackagingBox(id: 1, width: 3.0, height: 3.0, length: 3.0, maxWeight: 10.0)],
            requestHash: 'hash-3',
        );
    }

    public function testItDetectsFailoverLoop(): void
    {
        $registry = new ConfigurablePackingPolicyRegistry(
            resolvedSource: 'provider',
            policiesBySource: [
                'provider' => new ConfigurablePackingPolicy(
                    throwMessage: 'provider down',
                    source: 'provider',
                    failoverSource: 'manual',
                ),
                'manual' => new ConfigurablePackingPolicy(
                    throwMessage: 'manual down',
                    source: 'manual',
                    failoverSource: 'provider',
                ),
            ],
        );
        $service = new CalculateBoxSize(
            packingPolicyRegistry: $registry,
            logger: new InMemoryLogger(),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Packing policy failover loop detected.');

        $service->calculate(
            request: $this->request(),
            boxes: [new PackagingBox(id: 1, width: 3.0, height: 3.0, length: 3.0, maxWeight: 10.0)],
            requestHash: 'hash-4',
        );
    }

    private function request(): PackingRequest
    {
        return new PackingRequest([
            new ProductToPack(
                dimensions: new Dimensions(width: 1.0, height: 1.0, length: 1.0),
                weight: new Weight(valueKg: 1.0),
            ),
        ]);
    }
}
