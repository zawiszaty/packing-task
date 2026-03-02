<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class Weight
{
    public function __construct(public float $valueKg)
    {
    }
}
