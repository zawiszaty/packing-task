<?php

declare(strict_types=1);

namespace App\Application\Mapper;

use App\Application\DTO\PackProduct;
use App\Application\DTO\PackProductsCommand;
use App\Domain\ValueObject\Dimensions;
use App\Domain\ValueObject\PackingRequest;
use App\Domain\ValueObject\ProductToPack;
use App\Domain\ValueObject\Weight;

final class PackProductsCommandMapper
{
    public function toPackingRequest(PackProductsCommand $command): PackingRequest
    {
        $products = array_map(
            static fn (PackProduct $product): ProductToPack => new ProductToPack(
                new Dimensions($product->width, $product->height, $product->length),
                new Weight($product->weight),
            ),
            $command->products,
        );

        return new PackingRequest($products);
    }
}
