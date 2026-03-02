<?php

declare(strict_types=1);

namespace App\Domain\Entity;

final readonly class PackagingBox
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
