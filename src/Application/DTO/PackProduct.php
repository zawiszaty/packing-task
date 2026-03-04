<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class PackProduct
{
    public function __construct(
        public float $width,
        public float $height,
        public float $length,
        public float $weight,
        public ?int $id = null,
    ) {
    }
}
