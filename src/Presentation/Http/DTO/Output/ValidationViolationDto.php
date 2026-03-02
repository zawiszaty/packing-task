<?php

declare(strict_types=1);

namespace App\Presentation\Http\DTO\Output;

final readonly class ValidationViolationDto
{
    public function __construct(
        public string $field,
        public string $message,
        public string $code,
    ) {
    }
}
