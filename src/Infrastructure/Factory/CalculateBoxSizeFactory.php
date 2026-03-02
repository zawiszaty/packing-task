<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Application\Mapper\PackProductsCommandMapper;
use App\Application\Mapper\StoredCalculationPayloadMapper;
use App\Application\Service\RequestHashBuilder;
use App\Application\UseCase\CalculateBoxSize;
use App\Domain\Policy\Refresh\ManualResultsRequireRefreshPolicy;
use App\Domain\Service\SimpleSmallestBoxSelector;
use App\Infrastructure\CircuitBreaker\CircuitBreaker;
use App\Infrastructure\Persistence\Doctrine\DoctrinePackingCalculationRepository;
use App\Infrastructure\Policy\CircuitBreakerPackingPolicyRegistry;
use App\Infrastructure\Policy\ManualPackingPolicy;
use App\Infrastructure\Policy\ProviderPackingPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class CalculateBoxSizeFactory
{
    public static function create(
        EntityManagerInterface $entityManager,
        CircuitBreaker $circuitBreaker,
        LoggerInterface $logger,
        string $projectDir,
    ): CalculateBoxSize {
        $providerClient = ThreeDBinPackingClientFactory::create($projectDir, $logger);
        $manualPolicy = new ManualPackingPolicy(new SimpleSmallestBoxSelector());
        $providerPolicy = new ProviderPackingPolicy($providerClient, $circuitBreaker);

        $policyRegistry = new CircuitBreakerPackingPolicyRegistry(
            $circuitBreaker,
            $providerPolicy,
            $manualPolicy,
        );

        return new CalculateBoxSize(
            $policyRegistry,
            new ManualResultsRequireRefreshPolicy(),
            PackagingRepositoryFactory::create($entityManager),
            new DoctrinePackingCalculationRepository($entityManager),
            new PackProductsCommandMapper(),
            new StoredCalculationPayloadMapper(),
            new RequestHashBuilder(),
            $logger,
        );
    }
}
