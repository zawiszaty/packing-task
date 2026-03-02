<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\Model;

final class PackApiResponseData
{
    public ?string $id = null;

    /** @var list<PackApiPackedBinResponse> */
    public array $bins_packed = [];

    /** @var list<PackApiNotPackedItemResponse>|null */
    public ?array $not_packed_items = null;

    /** @var list<PackApiErrorResponse>|null */
    public ?array $errors = null;

    public ?int $status = null;
}
