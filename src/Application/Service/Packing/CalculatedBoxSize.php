<?php

declare(strict_types=1);

namespace App\Application\Service\Packing;

use App\Domain\Entity\PackagingBox;

final readonly class CalculatedBoxSize
{
    public function __construct(
        public ?PackagingBox $selectedBox,
        public string $source,
    ) {
    }
}
