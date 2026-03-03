<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Infrastructure\Persistence\Doctrine\EntityManagerFactory;
use App\Presentation\Http\HttpApplication;
use App\Presentation\Http\SymfonyPackRequestResolver;

final class HttpApplicationFactory
{
    public static function create(string $projectDir): HttpApplication
    {
        $logger = LoggerFactory::create();
        $serializer = SerializerFactory::create();
        $validator = ValidatorFactory::create();
        $entityManager = EntityManagerFactory::create();
        $circuitBreaker = CircuitBreakerFactory::create($projectDir);

        return new HttpApplication(
            new SymfonyPackRequestResolver($serializer, $validator),
            FindBoxSizeFactory::create($entityManager, $circuitBreaker, $logger, $projectDir),
            $serializer,
            $logger,
        );
    }
}
