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
}
