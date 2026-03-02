<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\Stub;

use App\Domain\ValueObject\PackingRequest;
use App\Infrastructure\Provider\Model\PackResult;
use App\Infrastructure\Provider\ThreeDBinPackingClient;

final class StubThreeDBinPackingClient implements ThreeDBinPackingClient
{
    public function __construct(private readonly ?int $selectedBoxId = null)
    {
    }

    public function pack(PackingRequest $request, array $boxes): PackResult
    {
        return new PackResult($this->selectedBoxId);
    }
}
