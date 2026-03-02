<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\InMemory;

use App\Domain\Entity\PackagingBox;
use App\Domain\Repository\PackagingRepository;

final class InMemoryPackagingRepository implements PackagingRepository
{
    /** @var list<PackagingBox> */
    private array $boxes;

    /**
     * @param list<PackagingBox> $boxes
     */
    public function __construct(array $boxes)
    {
        $this->boxes = $boxes;
    }

    public function findAll(): array
    {
        return $this->boxes;
    }
}
