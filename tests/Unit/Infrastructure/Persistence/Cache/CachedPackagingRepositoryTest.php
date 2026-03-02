<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Cache;

use App\Domain\Entity\PackagingBox;
use App\Infrastructure\Persistence\Cache\CachedPackagingRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tests\Support\Fake\Domain\Repository\ConfigurablePackagingRepository;

final class CachedPackagingRepositoryTest extends TestCase
{
    public function testItLoadsFromInnerRepositoryOnlyOnce(): void
    {
        $innerRepository = new ConfigurablePackagingRepository(boxes: [
            new PackagingBox(id: 1, width: 1.0, height: 1.0, length: 1.0, maxWeight: 1.0),
        ]);
        $cachedPackagingRepository = new CachedPackagingRepository(
            inner: $innerRepository,
            cache: new ArrayAdapter(),
        );

        $first = $cachedPackagingRepository->findAll();
        $second = $cachedPackagingRepository->findAll();

        self::assertCount(1, $first);
        self::assertCount(1, $second);
        self::assertSame(1, $innerRepository->findAllCalls);
    }
}
