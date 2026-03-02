<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\PackingCalculation;

interface PackingCalculationRepository
{
    public function findLatestByInputHash(string $inputHash): ?PackingCalculation;

    public function save(PackingCalculation $calculation): void;
}
