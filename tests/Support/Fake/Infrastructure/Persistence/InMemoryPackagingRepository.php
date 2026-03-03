<?php

declare(strict_types=1);

namespace Tests\Support\Fake\Infrastructure\Persistence;

use App\Domain\Entity\PackagingBox;
use App\Domain\Repository\PackagingRepository;

final class InMemoryPackagingRepository implements PackagingRepository
{
    /** @var list<PackagingBox> */
    private array $boxes;

    /** @var array<int, PackagingBox> */
    private array $boxesById = [];

    /**
     * @param list<PackagingBox> $boxes
     */
    public function __construct(array $boxes)
    {
        $this->boxes = $boxes;

        foreach ($boxes as $box) {
            $this->boxesById[$box->id] = $box;
        }
    }

    public function findAll(): array
    {
        return $this->boxes;
    }

    public function findById(int $boxId): ?PackagingBox
    {
        return $this->boxesById[$boxId] ?? null;
    }
}
