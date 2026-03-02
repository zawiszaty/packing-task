<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\Payload;

use App\Domain\Entity\PackagingBox;
use App\Domain\ValueObject\PackingRequest;

final readonly class PackIntoManyRequestPayload
{
    /**
     * @param list<ItemPayload> $items
     * @param list<BinPayload> $bins
     */
    private function __construct(
        private string $username,
        private string $apiKey,
        private array $items,
        private array $bins,
    ) {
    }

    /**
     * @param list<PackagingBox> $boxes
     */
    public static function fromDomain(string $username, string $apiKey, PackingRequest $request, array $boxes): self
    {
        $items = [];
        foreach ($request->products as $index => $product) {
            $items[] = ItemPayload::fromProduct($product, $index);
        }

        $bins = [];
        foreach ($boxes as $box) {
            $bins[] = BinPayload::fromBox($box);
        }

        return new self($username, $apiKey, $items, $bins);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'api_key' => $this->apiKey,
            'items' => array_map(static fn (ItemPayload $item): array => $item->toArray(), $this->items),
            'bins' => array_map(static fn (BinPayload $bin): array => $bin->toArray(), $this->bins),
            'params' => [
                'images_complete' => 0,
                'images_sbs' => 0,
                'images_separated' => 0,
                'item_coordinates' => 0,
                'stats' => 0,
            ],
        ];
    }
}
