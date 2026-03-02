<?php

declare(strict_types=1);

namespace App\Presentation\Http\DTO\Output;

final readonly class PackResponseDto
{
    public function __construct(
        public string $outcome,
        public ?string $reason,
        public ?string $message,
        public ?PackMetaResponseDto $meta = null,
        public ?ProblemDetailsDto $problem = null,
        public ?PackResultResponseDto $result = null,
    ) {
    }
}
