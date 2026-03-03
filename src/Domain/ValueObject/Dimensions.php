<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use InvalidArgumentException;

final readonly class Dimensions
{
    public function __construct(
        public float $width,
        public float $height,
        public float $length,
    ) {
        if (!is_finite($this->width) || $this->width <= 0.0) {
            throw new InvalidArgumentException('Width must be a finite number greater than 0.');
        }

        if (!is_finite($this->height) || $this->height <= 0.0) {
            throw new InvalidArgumentException('Height must be a finite number greater than 0.');
        }

        if (!is_finite($this->length) || $this->length <= 0.0) {
            throw new InvalidArgumentException('Length must be a finite number greater than 0.');
        }
    }
}
