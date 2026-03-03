<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\Model;

final class PackApiPackedItemResponse
{
    public ?string $id = null;
    public ?float $w = null;
    public ?float $h = null;
    public ?float $d = null;
    public ?float $wg = null;
    public ?string $image_sbs = null;
    public ?PackApiCoordinatesResponse $coordinates = null;
}
