<?php

declare(strict_types=1);

namespace App\Infrastructure\CircuitBreaker\Simple;

use App\Infrastructure\CircuitBreaker\CircuitBreaker;

final class StaticCircuitBreaker implements CircuitBreaker
{
    public function __construct(private readonly bool $available = true)
    {
    }

    public function isAvailable(string $serviceName): bool
    {
        return $this->available;
    }

    public function success(string $serviceName): void
    {
    }

    public function failure(string $serviceName): void
    {
    }
}
