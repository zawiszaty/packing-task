<?php

declare(strict_types=1);

namespace App\Infrastructure\CircuitBreaker;

interface CircuitBreaker
{
    public function isAvailable(string $serviceName): bool;

    public function success(string $serviceName): void;

    public function failure(string $serviceName): void;
}
