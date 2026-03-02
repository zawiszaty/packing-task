<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use Symfony\Component\Dotenv\Dotenv;

final class DotenvFactory
{
    private static bool $booted = false;

    public static function boot(string $projectDir): void
    {
        if (self::$booted) {
            return;
        }

        $envPath = $projectDir . '/.env';
        if (is_file($envPath)) {
            (new Dotenv())->bootEnv($envPath);
        }

        self::$booted = true;
    }

    public static function get(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;

        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
