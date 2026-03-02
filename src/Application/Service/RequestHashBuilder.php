<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\PackProduct;

final class RequestHashBuilder
{
    /**
     * @param list<PackProduct> $products
     */
    public function fromProducts(array $products): string
    {
        $normalized = array_map(
            static fn (PackProduct $product): array => [
                'width' => $product->width,
                'height' => $product->height,
                'length' => $product->length,
                'weight' => $product->weight,
            ],
            $products,
        );

        usort($normalized, static function (array $a, array $b): int {
            return [$a['width'], $a['height'], $a['length'], $a['weight']] <=> [$b['width'], $b['height'], $b['length'], $b['weight']];
        });

        return sha1(json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    public function fromRawPayload(string $payload): string
    {
        return sha1($payload);
    }
}
