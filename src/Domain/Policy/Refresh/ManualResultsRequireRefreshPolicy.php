<?php

declare(strict_types=1);

namespace App\Domain\Policy\Refresh;

use App\Domain\Entity\PackingCalculation;
use App\Domain\Policy\Packing\ProviderSelection;

final class ManualResultsRequireRefreshPolicy implements RequiresRefreshPolicy
{
    public function decide(PackingCalculation $calculation): RefreshDecision
    {
        return $calculation->providerSource === ProviderSelection::MANUAL->value
            ? RefreshDecision::REFRESH_REQUIRED
            : RefreshDecision::NO_REFRESH_NEEDED;
    }
}
