<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Domain\Repository\PackagingRepository;
use App\Infrastructure\Persistence\Cache\CachedPackagingRepository;
use App\Infrastructure\Persistence\Doctrine\DoctrinePackagingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

final class PackagingRepositoryFactory
{
    public static function create(EntityManagerInterface $entityManager): PackagingRepository
    {
        return new CachedPackagingRepository(
            new DoctrinePackagingRepository($entityManager),
            new FilesystemAdapter(namespace: 'packaging_boxes_forever'),
        );
    }
}
