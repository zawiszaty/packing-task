<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Service;

use App\Application\DTO\PackProduct;
use App\Application\Service\RequestHashBuilder;
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
            new PackProduct(width: 1.0, height: 2.0, length: 3.0, weight: 4.0),
            new PackProduct(width: 2.0, height: 3.0, length: 4.0, weight: 5.0),
        ];
        $secondOrder = [
            new PackProduct(width: 2.0, height: 3.0, length: 4.0, weight: 5.0),
            new PackProduct(width: 1.0, height: 2.0, length: 3.0, weight: 4.0),
        ];

        self::assertSame(
            $this->requestHashBuilder->fromProducts(products: $firstOrder),
            $this->requestHashBuilder->fromProducts(products: $secondOrder),
        );
    }

    public function testItReturnsDifferentHashWhenProductPayloadDiffers(): void
    {
        $base = [new PackProduct(width: 1.0, height: 2.0, length: 3.0, weight: 4.0)];
        $changed = [new PackProduct(width: 1.1, height: 2.0, length: 3.0, weight: 4.0)];

        self::assertNotSame(
            $this->requestHashBuilder->fromProducts(products: $base),
            $this->requestHashBuilder->fromProducts(products: $changed),
        );
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
            new PackProduct(width: 2.0, height: 1.0, length: 4.0, weight: 3.0),
            new PackProduct(width: 1.0, height: 1.0, length: 1.0, weight: 1.0),
        ];

        self::assertSame(
            'a04db3352aab86fc0046155d90fbf13da29ba5a7',
            $this->requestHashBuilder->fromProducts(products: $products),
        );
    }
}
