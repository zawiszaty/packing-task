<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class ProductToPack
{
    public function __construct(
        public Dimensions $dimensions,
        public Weight $weight,
    ) {
    }
}
