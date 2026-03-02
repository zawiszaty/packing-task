<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\Payload;

use App\Domain\Entity\PackagingBox;

final readonly class BinPayload
{
    public function __construct(
        private string $id,
        private float $width,
        private float $height,
        private float $depth,
        private float $maxWeight,
        private int $quantity,
        private int $cost,
        private string $type,
    ) {
    }

    public static function fromBox(PackagingBox $box): self
    {
        return new self(
            id: (string) $box->id,
            width: $box->width,
            height: $box->height,
            depth: $box->length,
            maxWeight: $box->maxWeight,
            quantity: 1,
            cost: 0,
            type: 'box',
        );
    }

    /**
     * @return array{id: string, w: float, h: float, d: float, max_wg: float, q: int, cost: int, type: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'w' => $this->width,
            'h' => $this->height,
            'd' => $this->depth,
            'max_wg' => $this->maxWeight,
            'q' => $this->quantity,
            'cost' => $this->cost,
            'type' => $this->type,
        ];
    }
}
