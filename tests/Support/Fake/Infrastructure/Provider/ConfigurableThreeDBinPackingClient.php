<?php

declare(strict_types=1);

namespace Tests\Support\Fake\Infrastructure\Provider;

use App\Domain\ValueObject\PackingRequest;
use App\Infrastructure\Provider\Model\PackResult;
use App\Infrastructure\Provider\ThreeDBinPackingClient;

final class ConfigurableThreeDBinPackingClient implements ThreeDBinPackingClient
{
    public function __construct(
        private readonly ?int $selectedBoxId = null,
        private readonly ?string $throwMessage = null,
    ) {
    }

    public function pack(PackingRequest $request, array $boxes): PackResult
    {
        if ($this->throwMessage !== null) {
            throw new \RuntimeException($this->throwMessage);
        }

        return new PackResult(selectedBoxId: $this->selectedBoxId);
    }
}
