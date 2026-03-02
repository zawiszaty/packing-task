<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Entity\PackingCalculation as DomainPackingCalculation;
use App\Domain\Repository\PackingCalculationRepository;
use App\Infrastructure\Persistence\Doctrine\Entity\PackingCalculation;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrinePackingCalculationRepository implements PackingCalculationRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findLatestByInputHash(string $inputHash): ?DomainPackingCalculation
    {
        $entity = $this->entityManager->getRepository(PackingCalculation::class)
            ->createQueryBuilder('c')
            ->where('c.inputHash = :inputHash')
            ->setParameter('inputHash', $inputHash)
            ->orderBy('c.createdAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$entity instanceof PackingCalculation) {
            return null;
        }

        return new DomainPackingCalculation(
            id: (int) $entity->getId(),
            inputHash: $entity->getInputHash(),
            normalizedRequest: $entity->getNormalizedRequest(),
            normalizedResult: $entity->getNormalizedResult(),
            selectedBoxId: $entity->getSelectedBoxId(),
            providerSource: $entity->getProviderSource(),
            createdAt: $entity->getCreatedAt(),
            refreshedAt: $entity->getRefreshedAt(),
        );
    }

    public function save(DomainPackingCalculation $calculation): void
    {
        $entity = new PackingCalculation(
            inputHash: $calculation->inputHash,
            normalizedRequest: $calculation->normalizedRequest,
            normalizedResult: $calculation->normalizedResult,
            selectedBoxId: $calculation->selectedBoxId,
            providerSource: $calculation->providerSource,
            createdAt: $calculation->createdAt,
            refreshedAt: $calculation->refreshedAt,
        );

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }
}
