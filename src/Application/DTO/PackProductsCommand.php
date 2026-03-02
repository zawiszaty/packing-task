<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * @param list<PackProduct> $products
 */
final readonly class PackProductsCommand
{
    /**
     * @param list<PackProduct> $products
     */
    public function __construct(public array $products)
    {
    }
}
