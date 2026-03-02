<?php

declare(strict_types=1);

namespace Tests\Functional\Infrastructure\Persistence\Doctrine;

use App\Infrastructure\Persistence\Doctrine\DoctrinePackagingRepository;
use App\Infrastructure\Persistence\Doctrine\Entity\Packaging;
use Tests\Functional\Support\MySqlFunctionalTestCase;

final class DoctrinePackagingRepositoryFunctionalTest extends MySqlFunctionalTestCase
{
    public function testItLoadsAllBoxesFromMySql(): void
    {
        $this->entityManager->persist(new Packaging(2.2, 3.3, 4.4, 9.9));
        $this->entityManager->persist(new Packaging(5.5, 6.6, 7.7, 8.8));
        $this->entityManager->flush();

        $boxes = (new DoctrinePackagingRepository($this->entityManager))->findAll();

        self::assertCount(2, $boxes);
        self::assertSame(2.2, $boxes[0]->width);
        self::assertSame(8.8, $boxes[1]->maxWeight);
        self::assertGreaterThan(0, $boxes[0]->id);
    }
}
