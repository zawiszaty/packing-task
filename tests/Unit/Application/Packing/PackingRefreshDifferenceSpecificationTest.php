<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Packing;

use App\Application\Service\Packing\PackingRefreshDifferenceSpecification;
use App\Application\Service\Packing\PackingResultDifference;
use PHPUnit\Framework\TestCase;

final class PackingRefreshDifferenceSpecificationTest extends TestCase
{
    private PackingRefreshDifferenceSpecification $specification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->specification = new PackingRefreshDifferenceSpecification();
    }

    public function testItReturnsUnchangedWhenSelectedBoxIdsAreEqual(): void
    {
        self::assertSame(
            PackingResultDifference::UNCHANGED,
            $this->specification->compare(previousSelectedBoxId: 1, refreshedSelectedBoxId: 1),
        );
        self::assertSame(
            PackingResultDifference::UNCHANGED,
            $this->specification->compare(previousSelectedBoxId: null, refreshedSelectedBoxId: null),
        );
    }

    public function testItReturnsImprovedWhenNoBoxBecomesBox(): void
    {
        self::assertSame(
            PackingResultDifference::IMPROVED,
            $this->specification->compare(previousSelectedBoxId: null, refreshedSelectedBoxId: 2),
        );
    }

    public function testItReturnsRegressedWhenBoxBecomesNoBox(): void
    {
        self::assertSame(
            PackingResultDifference::REGRESSED,
            $this->specification->compare(previousSelectedBoxId: 2, refreshedSelectedBoxId: null),
        );
    }

    public function testItReturnsChangedWhenDifferentBoxesAreSelected(): void
    {
        self::assertSame(
            PackingResultDifference::CHANGED,
            $this->specification->compare(previousSelectedBoxId: 1, refreshedSelectedBoxId: 2),
        );
    }
}
