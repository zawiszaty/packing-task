<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class SelectedBox
{
    public function __construct(
        public int $id,
        public float $width,
        public float $height,
        public float $length,
        public float $maxWeight,
    ) {
    }
}
