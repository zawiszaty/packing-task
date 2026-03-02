<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Infrastructure\CircuitBreaker\CircuitBreaker;
use App\Infrastructure\CircuitBreaker\GaneshaApcuCircuitBreaker;

final class CircuitBreakerFactory
{
    public static function create(?string $projectDir = null): CircuitBreaker
    {
        DotenvFactory::boot($projectDir ?? dirname(__DIR__, 3));

        return new GaneshaApcuCircuitBreaker(
            failureRateThreshold: self::envInt('CB_FAILURE_RATE_THRESHOLD', 50),
            minimumRequests: self::envInt('CB_MINIMUM_REQUESTS', 10),
            intervalToHalfOpen: self::envInt('CB_INTERVAL_TO_HALF_OPEN', 5),
            timeWindow: self::envInt('CB_TIME_WINDOW', 30),
        );
    }

    private static function envInt(string $name, int $default): int
    {
        $value = DotenvFactory::get($name);

        if ($value === null) {
            return $default;
        }

        $intValue = filter_var($value, FILTER_VALIDATE_INT);

        if ($intValue === false) {
            return $default;
        }

        return $intValue;
    }
}
