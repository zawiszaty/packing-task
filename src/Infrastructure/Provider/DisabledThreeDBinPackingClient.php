<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use App\Domain\ValueObject\PackingRequest;
use App\Infrastructure\Provider\Model\PackResult;
use RuntimeException;

final class DisabledThreeDBinPackingClient implements ThreeDBinPackingClient
{
    public function __construct(private readonly string $reason)
    {
    }

    public function pack(PackingRequest $request, array $boxes): PackResult
    {
        throw new RuntimeException($this->reason);
    }
}
