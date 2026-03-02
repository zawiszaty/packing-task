<?php

declare(strict_types=1);

namespace App\Presentation\Http\DTO\Output;

final readonly class PackDataDto
{
    public function __construct(
        public string $type,
        public string $id,
        public PackingDecisionAttributesDto $attributes,
    ) {
    }
}
