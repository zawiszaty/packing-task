<?php

declare(strict_types=1);

namespace App\Application\Packing;

use App\Domain\Entity\PackagingBox;

final readonly class CalculatedBoxSizeResult
{
    public function __construct(
        public ?PackagingBox $selectedBox,
        public string $source,
    ) {
    }
}
