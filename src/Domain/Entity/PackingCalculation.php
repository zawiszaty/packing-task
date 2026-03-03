<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Policy\Refresh\RefreshDecision;
use App\Domain\Policy\Refresh\RequiresRefreshPolicy;
use DateTimeImmutable;

final readonly class PackingCalculation
{
    public function __construct(
        public int $id,
        public string $inputHash,
        public string $normalizedRequest,
        public string $normalizedResult,
        public ?int $selectedBoxId,
        public string $providerSource,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $refreshedAt,
    ) {
    }

    public function requiresRefresh(RequiresRefreshPolicy $policy): bool
    {
        return $policy->decide($this) === RefreshDecision::REFRESH_REQUIRED;
    }
}
