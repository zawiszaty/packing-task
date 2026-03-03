<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class Weight
{
    public function __construct(public float $valueKg)
    {
        if (!is_finite($this->valueKg) || $this->valueKg <= 0.0) {
            throw new \InvalidArgumentException('Weight must be a finite number greater than 0.');
        }
    }
}
