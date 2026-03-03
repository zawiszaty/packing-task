<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\CircuitBreaker\Simple;

use App\Infrastructure\CircuitBreaker\Simple\StaticCircuitBreaker;
use PHPUnit\Framework\TestCase;

final class StaticCircuitBreakerTest extends TestCase
{
    public function testItIsAvailableByDefault(): void
    {
        $circuitBreaker = new StaticCircuitBreaker();

        self::assertTrue($circuitBreaker->isAvailable('3dbinpacking'));
    }
}
