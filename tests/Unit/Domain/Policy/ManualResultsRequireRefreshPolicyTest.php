<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Policy;

use App\Domain\Entity\PackingCalculation;
use App\Domain\Policy\Packing\ProviderSelection;
use App\Domain\Policy\Refresh\ManualResultsRequireRefreshPolicy;
use App\Domain\Policy\Refresh\RefreshDecision;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ManualResultsRequireRefreshPolicyTest extends TestCase
{
    public function testItRequiresRefreshForManualProvider(): void
    {
        $policy = new ManualResultsRequireRefreshPolicy();
        $calculation = new PackingCalculation(
            id: 1,
            inputHash: 'hash',
            normalizedRequest: '{}',
            normalizedResult: '{}',
            selectedBoxId: null,
            providerSource: ProviderSelection::MANUAL->value,
            createdAt: new DateTimeImmutable(),
            refreshedAt: null,
        );

        self::assertSame(RefreshDecision::REFRESH_REQUIRED, $policy->decide($calculation));
    }

    public function testItDoesNotRequireRefreshForProviderResult(): void
    {
        $policy = new ManualResultsRequireRefreshPolicy();
        $calculation = new PackingCalculation(
            id: 2,
            inputHash: 'hash2',
            normalizedRequest: '{}',
            normalizedResult: '{}',
            selectedBoxId: 10,
            providerSource: ProviderSelection::PROVIDER_3D_BIN_PACKING->value,
            createdAt: new DateTimeImmutable(),
            refreshedAt: null,
        );

        self::assertSame(RefreshDecision::NO_REFRESH_NEEDED, $policy->decide($calculation));
    }
}
