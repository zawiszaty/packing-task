<?php

declare(strict_types=1);

namespace App\Presentation\Http\DTO\Output;

final readonly class PackingDecisionAttributesDto
{
    public function __construct(
        public string $outcome,
        public ?string $reason,
        public ?string $message,
        public ?PackBoxResponseDto $box = null,
    ) {
    }
}
