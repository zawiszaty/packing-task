<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Entity\PackagingBox;
use App\Domain\Repository\PackagingRepository;
use App\Infrastructure\Persistence\Doctrine\Entity\Packaging;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrinePackagingRepository implements PackagingRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findAll(): array
    {
        $entities = $this->entityManager->getRepository(Packaging::class)->findAll();

        return array_map(
            static fn (Packaging $packaging): PackagingBox => new PackagingBox(
                id: (int) $packaging->getId(),
                width: $packaging->getWidth(),
                height: $packaging->getHeight(),
                length: $packaging->getLength(),
                maxWeight: $packaging->getMaxWeight(),
            ),
            $entities,
        );
    }

    public function findById(int $boxId): ?PackagingBox
    {
        $entity = $this->entityManager->getRepository(Packaging::class)->find($boxId);
        if (!$entity instanceof Packaging) {
            return null;
        }

        return new PackagingBox(
            id: (int) $entity->getId(),
            width: $entity->getWidth(),
            height: $entity->getHeight(),
            length: $entity->getLength(),
            maxWeight: $entity->getMaxWeight(),
        );
    }
}
