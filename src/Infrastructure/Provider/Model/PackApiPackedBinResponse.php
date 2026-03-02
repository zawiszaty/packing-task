<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\Model;

final class PackApiPackedBinResponse
{
    public ?PackApiPackedBinData $bin_data = null;

    public ?string $image_complete = null;

    /** @var list<PackApiPackedItemResponse>|null */
    public ?array $items = null;
}
