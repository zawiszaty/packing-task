<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\PackProduct;
use InvalidArgumentException;

final class RequestHashBuilder
{
    /**
     * @param list<PackProduct> $products
     */
    public function fromProducts(array $products): string
    {
        /** @var array<string, int> $grouped */
        $grouped = [];
        foreach ($products as $product) {
            $productKey = $this->productKey($product);
            $grouped[$productKey] = ($grouped[$productKey] ?? 0) + 1;
        }

        ksort($grouped);

        return sha1(json_encode($grouped, JSON_THROW_ON_ERROR));
    }

    public function fromRawPayload(string $payload): string
    {
        return sha1($payload);
    }

    private function productKey(PackProduct $product): string
    {
        if ($product->id === null) {
            throw new InvalidArgumentException('Product id is required to build request hash.');
        }

        return sprintf('id:%d', $product->id);
    }
}
