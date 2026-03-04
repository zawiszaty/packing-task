<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Service;

use App\Application\DTO\PackProduct;
use App\Application\Service\RequestHashBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RequestHashBuilderTest extends TestCase
{
    private RequestHashBuilder $requestHashBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requestHashBuilder = new RequestHashBuilder();
    }

    public function testItBuildsOrderInsensitiveHashForProducts(): void
    {
        $firstOrder = [
            new PackProduct(width: 1.0, height: 2.0, length: 3.0, weight: 4.0, id: 10),
            new PackProduct(width: 2.0, height: 3.0, length: 4.0, weight: 5.0, id: 20),
        ];
        $secondOrder = [
            new PackProduct(width: 2.0, height: 3.0, length: 4.0, weight: 5.0, id: 20),
            new PackProduct(width: 1.0, height: 2.0, length: 3.0, weight: 4.0, id: 10),
        ];

        self::assertSame(
            $this->requestHashBuilder->fromProducts(products: $firstOrder),
            $this->requestHashBuilder->fromProducts(products: $secondOrder),
        );
    }

    public function testItReturnsDifferentHashWhenProductIdDiffers(): void
    {
        $base = [new PackProduct(width: 1.0, height: 2.0, length: 3.0, weight: 4.0, id: 1)];
        $changed = [new PackProduct(width: 1.0, height: 2.0, length: 3.0, weight: 4.0, id: 2)];

        self::assertNotSame(
            $this->requestHashBuilder->fromProducts(products: $base),
            $this->requestHashBuilder->fromProducts(products: $changed),
        );
    }

    public function testItUsesProductIdInsteadOfDimensionsWhenIdIsProvided(): void
    {
        $base = [new PackProduct(width: 1.0, height: 2.0, length: 3.0, weight: 4.0, id: 42)];
        $changedDimensions = [new PackProduct(width: 9.0, height: 9.0, length: 9.0, weight: 9.0, id: 42)];

        self::assertSame(
            $this->requestHashBuilder->fromProducts(products: $base),
            $this->requestHashBuilder->fromProducts(products: $changedDimensions),
        );
    }

    public function testItThrowsWhenProductIdIsMissing(): void
    {
        $products = [new PackProduct(width: 1.0, height: 2.0, length: 3.0, weight: 4.0)];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product id is required to build request hash.');

        $this->requestHashBuilder->fromProducts(products: $products);
    }

    public function testItBuildsStableRawPayloadHash(): void
    {
        self::assertSame(
            $this->requestHashBuilder->fromRawPayload(payload: '{"products":[1]}'),
            $this->requestHashBuilder->fromRawPayload(payload: '{"products":[1]}'),
        );
    }

    public function testItBuildsExpectedCanonicalHashForKnownPayload(): void
    {
        $products = [
            new PackProduct(width: 2.0, height: 1.0, length: 4.0, weight: 3.0, id: 2),
            new PackProduct(width: 1.0, height: 1.0, length: 1.0, weight: 1.0, id: 1),
        ];

        self::assertSame(
            '988eb963c08ce9fb21cc9c5d388961ba2ba496e8',
            $this->requestHashBuilder->fromProducts(products: $products),
        );
    }
}
