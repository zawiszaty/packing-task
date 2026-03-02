<?php

declare(strict_types=1);

namespace App\Domain\Policy\Refresh;

use App\Domain\Entity\PackingCalculation;

interface RequiresRefreshPolicy
{
    public function decide(PackingCalculation $calculation): RefreshDecision;
}
