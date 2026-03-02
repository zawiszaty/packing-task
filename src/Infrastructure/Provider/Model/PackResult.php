<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\Model;

final readonly class PackResult
{
    public function __construct(private ?int $selectedBoxId)
    {
    }

    public function selectedBoxId(): ?int
    {
        return $this->selectedBoxId;
    }
}
