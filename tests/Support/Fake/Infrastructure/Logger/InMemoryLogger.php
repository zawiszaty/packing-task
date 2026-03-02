<?php

declare(strict_types=1);

namespace Tests\Support\Fake\Infrastructure\Logger;

use Psr\Log\AbstractLogger;
use Stringable;

final class InMemoryLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<array-key, mixed>}> */
    private array $records = [];

    /**
     * @param array<array-key, mixed> $context
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $normalizedLevel = is_string($level)
            ? $level
            : (is_object($level) && method_exists($level, '__toString') ? (string) $level : 'unknown');

        $this->records[] = [
            'level' => $normalizedLevel,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @return list<array{level: string, message: string, context: array<array-key, mixed>}>
     */
    public function recordsBy(string $level, string $message): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (array $record): bool => $record['level'] === $level && $record['message'] === $message,
        ));
    }
}
