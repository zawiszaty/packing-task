<?php

declare(strict_types=1);

namespace App\Presentation\Http\DTO\Output;

final readonly class ApiErrorDto
{
    public function __construct(
        public string $status,
        public string $code,
        public string $title,
        public string $detail,
    ) {
    }
}
