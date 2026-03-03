<?php

declare(strict_types=1);

namespace Tests\Functional\Infrastructure\Persistence\Doctrine;

use App\Domain\Entity\PackingCalculation as DomainPackingCalculation;
use App\Infrastructure\Persistence\Doctrine\DoctrinePackingCalculationRepository;
use DateTimeImmutable;
use Tests\Functional\Support\MySqlFunctionalTestCase;

final class DoctrinePackingCalculationRepositoryFunctionalTest extends MySqlFunctionalTestCase
{
    private DoctrinePackingCalculationRepository $packingCalculationRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->packingCalculationRepository = new DoctrinePackingCalculationRepository($this->entityManager);
    }

    public function testItSavesAndReturnsLatestCalculationForHash(): void
    {
        $this->packingCalculationRepository->save(new DomainPackingCalculation(
            id: 0,
            inputHash: 'same-hash',
            normalizedRequest: '{"products":1}',
            normalizedResult: '{"outcome":"NO_BOX_RETURNED"}',
            selectedBoxId: null,
            providerSource: 'manual',
            createdAt: new DateTimeImmutable('2026-01-01 10:00:00'),
            refreshedAt: null,
        ));

        $this->packingCalculationRepository->save(new DomainPackingCalculation(
            id: 0,
            inputHash: 'same-hash',
            normalizedRequest: '{"products":1}',
            normalizedResult: '{"outcome":"BOX_RETURNED"}',
            selectedBoxId: 123,
            providerSource: 'provider_3dbinpacking',
            createdAt: new DateTimeImmutable('2026-01-01 11:00:00'),
            refreshedAt: new DateTimeImmutable('2026-01-01 11:05:00'),
        ));

        $latest = $this->packingCalculationRepository->findLatestByInputHash('same-hash');

        self::assertNotNull($latest);
        self::assertSame('provider_3dbinpacking', $latest->providerSource);
        self::assertSame(123, $latest->selectedBoxId);
        self::assertSame('{"outcome":"BOX_RETURNED"}', $latest->normalizedResult);
    }

    public function testItReturnsNullWhenHashDoesNotExist(): void
    {
        self::assertNull($this->packingCalculationRepository->findLatestByInputHash('missing-hash'));
    }
}
