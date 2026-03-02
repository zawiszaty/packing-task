<?php

declare(strict_types=1);

namespace Tests\Support\Fake\Domain\Repository;

use App\Domain\Entity\PackagingBox;
use App\Domain\Repository\PackagingRepository;

final class ConfigurablePackagingRepository implements PackagingRepository
{
    public int $findAllCalls = 0;

    /** @param list<PackagingBox> $boxes */
    public function __construct(
        private readonly array $boxes = [],
        private readonly ?string $throwMessage = null,
    ) {
    }

    public function findAll(): array
    {
        $this->findAllCalls++;

        if ($this->throwMessage !== null) {
            throw new \RuntimeException($this->throwMessage);
        }

        return $this->boxes;
    }
}
