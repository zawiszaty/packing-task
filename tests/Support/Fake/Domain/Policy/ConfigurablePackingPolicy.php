<?php

declare(strict_types=1);

namespace Tests\Support\Fake\Domain\Policy;

use App\Domain\Entity\PackagingBox;
use App\Domain\Policy\Packing\PackingPolicy;
use App\Domain\Policy\Packing\ProviderSelection;
use App\Domain\ValueObject\PackingRequest;
use RuntimeException;

final class ConfigurablePackingPolicy implements PackingPolicy
{
    public function __construct(
        private readonly ?string $throwMessage = null,
        private readonly string $source = ProviderSelection::PROVIDER_3D_BIN_PACKING->value,
        private readonly string $failoverSource = ProviderSelection::MANUAL->value,
    ) {
    }

    public function pack(PackingRequest $request, array $boxes): ?PackagingBox
    {
        if ($this->throwMessage !== null) {
            throw new RuntimeException($this->throwMessage);
        }

        return $boxes[0] ?? null;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function failoverPolicySource(): string
    {
        return $this->failoverSource;
    }
}
