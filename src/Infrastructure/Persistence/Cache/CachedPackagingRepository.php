<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Cache;

use App\Domain\Entity\PackagingBox;
use App\Domain\Repository\PackagingRepository;
use Psr\Cache\CacheItemPoolInterface;

final class CachedPackagingRepository implements PackagingRepository
{
    private const CACHE_KEY = 'packaging_boxes_all';

    public function __construct(
        private readonly PackagingRepository $inner,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function findAll(): array
    {
        $item = $this->cache->getItem(self::CACHE_KEY);

        if ($item->isHit()) {
            $cached = $item->get();
            if (is_array($cached)) {
                $rows = $this->normalizeRows($cached);
                if ($rows !== null) {
                    return $this->hydrate($rows);
                }
            }
        }

        $boxes = $this->inner->findAll();
        $item->set($this->dehydrate($boxes));
        $this->cache->save($item);

        return $boxes;
    }

    public function findById(int $boxId): ?PackagingBox
    {
        foreach ($this->findAll() as $box) {
            if ($box->id === $boxId) {
                return $box;
            }
        }

        return null;
    }

    /**
     * @param list<PackagingBox> $boxes
     * @return list<array{id: int, width: float, height: float, length: float, maxWeight: float}>
     */
    private function dehydrate(array $boxes): array
    {
        return array_map(
            static fn (PackagingBox $box): array => [
                'id' => $box->id,
                'width' => $box->width,
                'height' => $box->height,
                'length' => $box->length,
                'maxWeight' => $box->maxWeight,
            ],
            $boxes,
        );
    }

    /**
     * @param list<array{id: int, width: float, height: float, length: float, maxWeight: float}> $rows
     * @return list<PackagingBox>
     */
    private function hydrate(array $rows): array
    {
        return array_map(
            static fn (array $row): PackagingBox => new PackagingBox(
                id: (int) $row['id'],
                width: (float) $row['width'],
                height: (float) $row['height'],
                length: (float) $row['length'],
                maxWeight: (float) $row['maxWeight'],
            ),
            $rows,
        );
    }

    /**
     * @param array<mixed, mixed> $rows
     * @return list<array{id: int, width: float, height: float, length: float, maxWeight: float}>|null
     */
    private function normalizeRows(array $rows): ?array
    {
        $normalized = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                return null;
            }

            if (!isset($row['id'], $row['width'], $row['height'], $row['length'], $row['maxWeight'])) {
                return null;
            }

            if (
                !is_numeric($row['id'])
                || !is_numeric($row['width'])
                || !is_numeric($row['height'])
                || !is_numeric($row['length'])
                || !is_numeric($row['maxWeight'])
            ) {
                return null;
            }

            $normalized[] = [
                'id' => (int) $row['id'],
                'width' => (float) $row['width'],
                'height' => (float) $row['height'],
                'length' => (float) $row['length'],
                'maxWeight' => (float) $row['maxWeight'],
            ];
        }

        return $normalized;
    }
}
