<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class PackingDecision
{
    public function __construct(
        public CalculationOutcome $outcome,
        public ?SelectedBox $box,
        public ?string $reason,
        public string $source,
        public string $requestHash,
        public ?string $message = null,
    ) {
    }
}
