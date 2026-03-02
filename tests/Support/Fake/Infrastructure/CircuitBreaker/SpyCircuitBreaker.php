<?php

declare(strict_types=1);

namespace Tests\Support\Fake\Infrastructure\CircuitBreaker;

use App\Infrastructure\CircuitBreaker\CircuitBreaker;

final class SpyCircuitBreaker implements CircuitBreaker
{
    public int $successCalls = 0;
    public int $failureCalls = 0;

    public function isAvailable(string $serviceName): bool
    {
        return true;
    }

    public function success(string $serviceName): void
    {
        $this->successCalls++;
    }

    public function failure(string $serviceName): void
    {
        $this->failureCalls++;
    }
}
