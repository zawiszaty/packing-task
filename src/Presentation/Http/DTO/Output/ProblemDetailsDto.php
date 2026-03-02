<?php

declare(strict_types=1);

namespace App\Presentation\Http\DTO\Output;

final readonly class ProblemDetailsDto
{
    /**
     * @param list<ValidationViolationDto> $errors
     */
    public function __construct(
        public string $type,
        public string $title,
        public int $status,
        public string $detail,
        public array $errors = [],
    ) {
    }
}
