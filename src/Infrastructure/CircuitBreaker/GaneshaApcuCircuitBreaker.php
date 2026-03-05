<?php

declare(strict_types=1);

namespace App\Infrastructure\CircuitBreaker;

use Ackintosh\Ganesha;
use Ackintosh\Ganesha\Builder;
use Ackintosh\Ganesha\Storage\Adapter\Apcu;
use InvalidArgumentException;

final class GaneshaApcuCircuitBreaker implements CircuitBreaker
{
    private Ganesha $ganesha;

    public function __construct(
        int $failureRateThreshold = 50,
        int $minimumRequests = 10,
        int $intervalToHalfOpen = 60,
        int $timeWindow = 180,
    ) {
        if ($failureRateThreshold < 1 || $failureRateThreshold > 100) {
            throw new InvalidArgumentException('failureRateThreshold must be between 1 and 100.');
        }
        if ($minimumRequests < 1) {
            throw new InvalidArgumentException('minimumRequests must be greater than 0.');
        }
        if ($intervalToHalfOpen < 1) {
            throw new InvalidArgumentException('intervalToHalfOpen must be greater than 0.');
        }
        if ($timeWindow < 1) {
            throw new InvalidArgumentException('timeWindow must be greater than 0.');
        }

        $this->ganesha = Builder::withRateStrategy()
            ->adapter(new Apcu())
            ->failureRateThreshold($failureRateThreshold)
            ->minimumRequests($minimumRequests)
            ->intervalToHalfOpen($intervalToHalfOpen)
            ->timeWindow($timeWindow)
            ->build();
    }

    public function isAvailable(string $serviceName): bool
    {
        return $this->ganesha->isAvailable($serviceName);
    }

    public function success(string $serviceName): void
    {
        $this->ganesha->success($serviceName);
    }

    public function failure(string $serviceName): void
    {
        $this->ganesha->failure($serviceName);
    }
}
