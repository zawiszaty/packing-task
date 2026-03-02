<?php

declare(strict_types=1);

namespace App\Presentation\Http\DTO\Output;

final readonly class PackResultResponseDto
{
    public function __construct(public PackBoxResponseDto $box)
    {
    }
}
