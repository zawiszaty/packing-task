<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\Payload;

use App\Domain\ValueObject\ProductToPack;

final readonly class ItemPayload
{
    public function __construct(
        private string $id,
        private float $width,
        private float $height,
        private float $depth,
        private float $weight,
        private int $quantity,
        private bool $canRotateVertically,
    ) {
    }

    public static function fromProduct(ProductToPack $product, int $index): self
    {
        return new self(
            id: 'item_' . ($index + 1),
            width: $product->dimensions->width,
            height: $product->dimensions->height,
            depth: $product->dimensions->length,
            weight: $product->weight->valueKg,
            quantity: 1,
            canRotateVertically: true,
        );
    }

    /**
     * @return array{id: string, w: float, h: float, d: float, wg: float, q: int, vr: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'w' => $this->width,
            'h' => $this->height,
            'd' => $this->depth,
            'wg' => $this->weight,
            'q' => $this->quantity,
            'vr' => $this->canRotateVertically,
        ];
    }
}
