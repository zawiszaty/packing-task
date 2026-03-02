<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Service;

use App\Domain\Entity\PackagingBox;
use App\Domain\Service\SimpleSmallestBoxSelector;
use App\Domain\ValueObject\Dimensions;
use App\Domain\ValueObject\PackingRequest;
use App\Domain\ValueObject\ProductToPack;
use App\Domain\ValueObject\Weight;
use PHPUnit\Framework\TestCase;

final class SimpleSmallestBoxSelectorTest extends TestCase
{
    private SimpleSmallestBoxSelector $boxSelector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->boxSelector = new SimpleSmallestBoxSelector();
    }

    public function testItSelectsSmallestBoxThatFitsAllProducts(): void
    {
        $request = new PackingRequest([
            new ProductToPack(dimensions: new Dimensions(width: 2.0, height: 2.0, length: 2.0), weight: new Weight(valueKg: 1.0)),
            new ProductToPack(dimensions: new Dimensions(width: 3.0, height: 1.0, length: 1.0), weight: new Weight(valueKg: 1.0)),
        ]);

        $boxes = [
            new PackagingBox(id: 1, width: 4.0, height: 4.0, length: 4.0, maxWeight: 10.0),
            new PackagingBox(id: 2, width: 3.0, height: 3.0, length: 3.0, maxWeight: 10.0),
            new PackagingBox(id: 3, width: 10.0, height: 10.0, length: 10.0, maxWeight: 100.0),
        ];

        $selected = $this->boxSelector->select(request: $request, boxes: $boxes);

        self::assertNotNull($selected);
        self::assertSame(2, $selected->id);
    }

    public function testItReturnsNullWhenNoBoxFitsDimensions(): void
    {
        $request = new PackingRequest([
            new ProductToPack(dimensions: new Dimensions(width: 4.0, height: 4.0, length: 4.0), weight: new Weight(valueKg: 1.0)),
        ]);

        $selected = $this->boxSelector->select(request: $request, boxes: [
            new PackagingBox(id: 1, width: 3.0, height: 3.0, length: 3.0, maxWeight: 10.0),
        ]);

        self::assertNull($selected);
    }

    public function testItReturnsNullWhenWeightExceedsBoxLimit(): void
    {
        $request = new PackingRequest([
            new ProductToPack(dimensions: new Dimensions(width: 1.0, height: 1.0, length: 1.0), weight: new Weight(valueKg: 7.0)),
            new ProductToPack(dimensions: new Dimensions(width: 1.0, height: 1.0, length: 1.0), weight: new Weight(valueKg: 6.0)),
        ]);

        $selected = $this->boxSelector->select(request: $request, boxes: [
            new PackagingBox(id: 1, width: 5.0, height: 5.0, length: 5.0, maxWeight: 12.0),
        ]);

        self::assertNull($selected);
    }

    public function testItAcceptsFitByRotation(): void
    {
        $request = new PackingRequest([
            new ProductToPack(dimensions: new Dimensions(width: 4.0, height: 2.0, length: 3.0), weight: new Weight(valueKg: 1.0)),
        ]);

        $selected = $this->boxSelector->select(request: $request, boxes: [
            new PackagingBox(id: 1, width: 3.0, height: 4.0, length: 2.0, maxWeight: 10.0),
        ]);

        self::assertNotNull($selected);
        self::assertSame(1, $selected->id);
    }

    public function testItReturnsNullForEmptyBoxesCollection(): void
    {
        $request = new PackingRequest([
            new ProductToPack(dimensions: new Dimensions(width: 1.0, height: 1.0, length: 1.0), weight: new Weight(valueKg: 1.0)),
        ]);

        self::assertNull($this->boxSelector->select(request: $request, boxes: []));
    }
}
