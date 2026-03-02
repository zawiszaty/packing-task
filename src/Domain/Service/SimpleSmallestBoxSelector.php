<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\PackagingBox;
use App\Domain\ValueObject\PackingRequest;

final class SimpleSmallestBoxSelector implements SmallestBoxSelector
{
    public function select(PackingRequest $request, array $boxes): ?PackagingBox
    {
        $candidates = array_values(array_filter(
            $boxes,
            fn (PackagingBox $box): bool => $this->canFitAllProducts($request, $box),
        ));

        if ($candidates === []) {
            return null;
        }

        usort(
            $candidates,
            static fn (PackagingBox $a, PackagingBox $b): int => $a->width * $a->height * $a->length <=> $b->width * $b->height * $b->length,
        );

        return $candidates[0];
    }

    private function canFitAllProducts(PackingRequest $request, PackagingBox $box): bool
    {
        $totalWeight = 0.0;

        foreach ($request->products as $product) {
            $totalWeight += $product->weight->valueKg;

            if (!$this->fitsByRotation(
                $product->dimensions->width,
                $product->dimensions->height,
                $product->dimensions->length,
                $box->width,
                $box->height,
                $box->length,
            )) {
                return false;
            }
        }

        return $totalWeight <= $box->maxWeight;
    }

    private function fitsByRotation(
        float $itemWidth,
        float $itemHeight,
        float $itemLength,
        float $boxWidth,
        float $boxHeight,
        float $boxLength,
    ): bool {
        $item = [$itemWidth, $itemHeight, $itemLength];
        $box = [$boxWidth, $boxHeight, $boxLength];

        sort($item);
        sort($box);

        return $item[0] <= $box[0] && $item[1] <= $box[1] && $item[2] <= $box[2];
    }
}
