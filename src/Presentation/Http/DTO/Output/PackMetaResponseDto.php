<?php

declare(strict_types=1);

namespace App\Presentation\Http\DTO\Output;

final readonly class PackMetaResponseDto
{
    public function __construct(
        public string $source,
        public string $requestHash,
    ) {
    }
}
