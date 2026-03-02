<?php

declare(strict_types=1);

namespace App\Infrastructure\Policy;

use App\Domain\Entity\PackagingBox;
use App\Domain\Policy\Packing\PackingPolicy;
use App\Domain\Policy\Packing\ProviderSelection;
use App\Domain\ValueObject\PackingRequest;
use App\Infrastructure\CircuitBreaker\CircuitBreaker;
use App\Infrastructure\Provider\ThreeDBinPackingClient;

final class ProviderPackingPolicy implements PackingPolicy
{
    private const SERVICE_NAME = '3dbinpacking';

    public function __construct(
        private readonly ThreeDBinPackingClient $providerClient,
        private readonly CircuitBreaker $circuitBreaker,
    ) {
    }

    public function pack(PackingRequest $request, array $boxes): ?PackagingBox
    {
        try {
            $result = $this->providerClient->pack($request, $boxes);
            $this->circuitBreaker->success(self::SERVICE_NAME);
        } catch (\Throwable $throwable) {
            $this->circuitBreaker->failure(self::SERVICE_NAME);
            throw $throwable;
        }

        $selectedBoxId = $result->selectedBoxId();

        if ($selectedBoxId === null) {
            return null;
        }

        foreach ($boxes as $box) {
            if ($box->id === $selectedBoxId) {
                return $box;
            }
        }

        return null;
    }

    public function source(): string
    {
        return ProviderSelection::PROVIDER_3D_BIN_PACKING->value;
    }

    public function failoverPolicySource(): string
    {
        return ProviderSelection::MANUAL->value;
    }
}
