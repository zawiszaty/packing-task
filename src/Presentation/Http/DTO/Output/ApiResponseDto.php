<?php

declare(strict_types=1);

namespace App\Presentation\Http\DTO\Output;

final readonly class ApiResponseDto
{
    /**
     * @param list<ApiErrorDto>|null $errors
     */
    public function __construct(
        public ?PackDataDto $data = null,
        public ?array $errors = null,
        public ?PackMetaResponseDto $meta = null,
    ) {
    }
}
