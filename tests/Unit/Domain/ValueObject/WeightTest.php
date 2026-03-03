<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\Weight;
use PHPUnit\Framework\TestCase;

final class WeightTest extends TestCase
{
    public function testItCreatesWeightForPositiveFiniteValue(): void
    {
        $weight = new Weight(valueKg: 2.5);

        self::assertSame(2.5, $weight->valueKg);
    }

    public function testItRejectsZeroOrNegativeWeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Weight must be a finite number greater than 0.');

        new Weight(valueKg: 0.0);
    }

    public function testItRejectsNonFiniteWeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Weight(valueKg: INF);
    }
}
