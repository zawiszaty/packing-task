<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class Dimensions
{
    public function __construct(
        public float $width,
        public float $height,
        public float $length,
    ) {
    }
}
