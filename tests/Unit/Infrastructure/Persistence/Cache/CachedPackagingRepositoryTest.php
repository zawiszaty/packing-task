<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Cache;

use App\Domain\Entity\PackagingBox;
use App\Domain\Repository\PackagingRepository;
use App\Infrastructure\Persistence\Cache\CachedPackagingRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CachedPackagingRepositoryTest extends TestCase
{
    public function testItCachesInnerResultAndReusesIt(): void
    {
        $innerRepository = new class () implements PackagingRepository {
            /** @var list<PackagingBox> */
            public array $boxes;
            public int $findAllCalls = 0;

            public function __construct()
            {
                $this->boxes = [
                    new PackagingBox(id: 1, width: 1.0, height: 2.0, length: 3.0, maxWeight: 4.0),
                ];
            }

            public function findAll(): array
            {
                ++$this->findAllCalls;

                return $this->boxes;
            }

            public function findById(int $boxId): ?PackagingBox
            {
                foreach ($this->boxes as $box) {
                    if ($box->id === $boxId) {
                        return $box;
                    }
                }

                return null;
            }
        };
        $cache = new ArrayAdapter();
        $cachedPackagingRepository = new CachedPackagingRepository(
            inner: $innerRepository,
            cache: $cache,
        );

        $first = $cachedPackagingRepository->findAll();
        $innerRepository->boxes = [
            new PackagingBox(id: 99, width: 9.0, height: 9.0, length: 9.0, maxWeight: 9.0),
        ];
        $second = $cachedPackagingRepository->findAll();

        self::assertSame(1, $innerRepository->findAllCalls);
        self::assertSame(1, $first[0]->id);
        self::assertSame(1, $second[0]->id);

        $item = $cache->getItem('packaging_boxes_all');
        self::assertTrue($item->isHit());
        self::assertSame([
            [
                'id' => 1,
                'width' => 1.0,
                'height' => 2.0,
                'length' => 3.0,
                'maxWeight' => 4.0,
            ],
        ], $item->get());
    }

    public function testItUsesValidCachedRowsWithoutCallingInnerRepository(): void
    {
        $innerRepository = new class () implements PackagingRepository {
            public int $findAllCalls = 0;

            public function findAll(): array
            {
                ++$this->findAllCalls;

                return [new PackagingBox(id: 777, width: 1, height: 1, length: 1, maxWeight: 1)];
            }

            public function findById(int $boxId): ?PackagingBox
            {
                return null;
            }
        };
        $cache = new ArrayAdapter();
        $item = $cache->getItem('packaging_boxes_all');
        $item->set([
            [
                'id' => '2',
                'width' => '10.5',
                'height' => '11.5',
                'length' => '12.5',
                'maxWeight' => '13.5',
            ],
        ]);
        $cache->save($item);

        $cachedPackagingRepository = new CachedPackagingRepository(
            inner: $innerRepository,
            cache: $cache,
        );

        $boxes = $cachedPackagingRepository->findAll();

        self::assertSame(0, $innerRepository->findAllCalls);
        self::assertCount(1, $boxes);
        self::assertSame(2, $boxes[0]->id);
        self::assertSame(10.5, $boxes[0]->width);
        self::assertSame(11.5, $boxes[0]->height);
        self::assertSame(12.5, $boxes[0]->length);
        self::assertSame(13.5, $boxes[0]->maxWeight);
    }

    public function testItFallsBackToInnerRepositoryWhenCachedPayloadIsInvalid(): void
    {
        $innerRepository = new class () implements PackagingRepository {
            public int $findAllCalls = 0;

            public function findAll(): array
            {
                ++$this->findAllCalls;

                return [
                    new PackagingBox(id: 3, width: 3.0, height: 3.0, length: 3.0, maxWeight: 3.0),
                ];
            }

            public function findById(int $boxId): ?PackagingBox
            {
                return null;
            }
        };
        $cache = new ArrayAdapter();
        $item = $cache->getItem('packaging_boxes_all');
        $item->set([
            [
                'id' => 1,
                'width' => 2.0,
                'height' => 3.0,
                'length' => 4.0,
                // missing maxWeight on purpose
            ],
        ]);
        $cache->save($item);

        $cachedPackagingRepository = new CachedPackagingRepository(
            inner: $innerRepository,
            cache: $cache,
        );

        $boxes = $cachedPackagingRepository->findAll();

        self::assertSame(1, $innerRepository->findAllCalls);
        self::assertCount(1, $boxes);
        self::assertSame(3, $boxes[0]->id);
    }

    public function testItFallsBackToInnerRepositoryWhenAnyCachedNumericFieldIsInvalid(): void
    {
        $invalidRows = [
            ['id' => 'x', 'width' => 1.0, 'height' => 1.0, 'length' => 1.0, 'maxWeight' => 1.0],
            ['id' => 1, 'width' => 'x', 'height' => 1.0, 'length' => 1.0, 'maxWeight' => 1.0],
            ['id' => 1, 'width' => 1.0, 'height' => 'x', 'length' => 1.0, 'maxWeight' => 1.0],
            ['id' => 1, 'width' => 1.0, 'height' => 1.0, 'length' => 'x', 'maxWeight' => 1.0],
            ['id' => 1, 'width' => 1.0, 'height' => 1.0, 'length' => 1.0, 'maxWeight' => 'x'],
        ];

        foreach ($invalidRows as $row) {
            $innerRepository = new class () implements PackagingRepository {
                public int $findAllCalls = 0;

                public function findAll(): array
                {
                    ++$this->findAllCalls;

                    return [
                        new PackagingBox(id: 42, width: 4.0, height: 4.0, length: 4.0, maxWeight: 4.0),
                    ];
                }

                public function findById(int $boxId): ?PackagingBox
                {
                    return null;
                }
            };
            $cache = new ArrayAdapter();
            $item = $cache->getItem('packaging_boxes_all');
            $item->set([$row]);
            $cache->save($item);

            $cachedPackagingRepository = new CachedPackagingRepository(
                inner: $innerRepository,
                cache: $cache,
            );

            $boxes = $cachedPackagingRepository->findAll();

            self::assertSame(1, $innerRepository->findAllCalls);
            self::assertSame(42, $boxes[0]->id);
        }
    }

    public function testFindByIdReturnsMatchingBoxOrNull(): void
    {
        $cachedPackagingRepository = new CachedPackagingRepository(
            inner: new class () implements PackagingRepository {
                public function findAll(): array
                {
                    return [
                        new PackagingBox(id: 10, width: 1.0, height: 1.0, length: 1.0, maxWeight: 1.0),
                        new PackagingBox(id: 20, width: 2.0, height: 2.0, length: 2.0, maxWeight: 2.0),
                    ];
                }

                public function findById(int $boxId): ?PackagingBox
                {
                    return null;
                }
            },
            cache: new ArrayAdapter(),
        );

        $box = $cachedPackagingRepository->findById(20);

        self::assertNotNull($box);
        self::assertSame(20, $box->id);
        self::assertNull($cachedPackagingRepository->findById(999));
    }
}
