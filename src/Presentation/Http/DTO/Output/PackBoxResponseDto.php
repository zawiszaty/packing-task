<?php

declare(strict_types=1);

namespace App\Presentation\Http\DTO\Output;

final readonly class PackBoxResponseDto
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
