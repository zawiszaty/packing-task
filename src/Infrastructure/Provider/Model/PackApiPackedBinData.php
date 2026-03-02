<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\Model;

final class PackApiPackedBinData
{
    public ?float $w = null;
    public ?float $h = null;
    public ?float $d = null;
    public int|string|null $id = null;
    public ?float $used_space = null;
    public ?float $weight = null;
    public ?float $gross_weight = null;
    public ?float $used_weight = null;
    public ?float $stack_height = null;
}
