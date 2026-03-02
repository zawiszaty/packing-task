<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\InMemory;

use App\Domain\Entity\PackingCalculation;
use App\Domain\Repository\PackingCalculationRepository;

final class InMemoryPackingCalculationRepository implements PackingCalculationRepository
{
    /** @var array<string, PackingCalculation> */
    private array $items = [];

    public function findLatestByInputHash(string $inputHash): ?PackingCalculation
    {
        return $this->items[$inputHash] ?? null;
    }

    public function save(PackingCalculation $calculation): void
    {
        $this->items[$calculation->inputHash] = $calculation;
    }
}
