<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * @param list<ProductToPack> $products
 */
final readonly class PackingRequest
{
    /**
     * @param list<ProductToPack> $products
     */
    public function __construct(public array $products)
    {
    }
}
