<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\Dimensions;
use PHPUnit\Framework\TestCase;

final class DimensionsTest extends TestCase
{
    public function testItCreatesDimensionsForPositiveFiniteValues(): void
    {
        $dimensions = new Dimensions(width: 1.2, height: 3.4, length: 5.6);

        self::assertSame(1.2, $dimensions->width);
        self::assertSame(3.4, $dimensions->height);
        self::assertSame(5.6, $dimensions->length);
    }

    public function testItRejectsZeroOrNegativeWidth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Width must be a finite number greater than 0.');

        new Dimensions(width: 0.0, height: 1.0, length: 1.0);
    }

    public function testItRejectsZeroOrNegativeHeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Height must be a finite number greater than 0.');

        new Dimensions(width: 1.0, height: -1.0, length: 1.0);
    }

    public function testItRejectsZeroOrNegativeLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Length must be a finite number greater than 0.');

        new Dimensions(width: 1.0, height: 1.0, length: 0.0);
    }

    public function testItRejectsNonFiniteValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Dimensions(width: INF, height: 1.0, length: 1.0);
    }
}
