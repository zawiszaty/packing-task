<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class StoredCalculationPayload
{
    public function __construct(
        public string $outcome,
        public ?string $reason,
        public ?string $message,
        public ?StoredBoxPayload $box,
    ) {
    }
}
