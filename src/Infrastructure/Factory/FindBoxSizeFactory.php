<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Application\Mapper\PackProductsCommandMapper;
use App\Application\Service\Packing\CalculateBoxSize;
use App\Application\Service\Packing\CalculateBoxSizeDecisionMapper;
use App\Application\Service\Packing\PackingRefreshDifferenceSpecification;
use App\Application\Service\Packing\RefreshPackingResult;
use App\Application\Service\Packing\StorePackingCalculation;
use App\Application\Service\RequestHashBuilder;
use App\Application\UseCase\FindBoxSize;
use App\Domain\Policy\Refresh\ManualResultsRequireRefreshPolicy;
use App\Domain\Service\SimpleSmallestBoxSelector;
use App\Infrastructure\CircuitBreaker\CircuitBreaker;
use App\Infrastructure\Persistence\Doctrine\DoctrinePackingCalculationRepository;
use App\Infrastructure\Policy\CircuitBreakerPackingPolicyRegistry;
use App\Infrastructure\Policy\ManualPackingPolicy;
use App\Infrastructure\Policy\ProviderPackingPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class FindBoxSizeFactory
{
    public static function create(
        EntityManagerInterface $entityManager,
        CircuitBreaker $circuitBreaker,
        LoggerInterface $logger,
        string $projectDir,
    ): FindBoxSize {
        $providerClient = ThreeDBinPackingClientFactory::create($projectDir, $logger);
        $manualPolicy = new ManualPackingPolicy(new SimpleSmallestBoxSelector());
        $providerPolicy = new ProviderPackingPolicy($providerClient, $circuitBreaker);
        $policyRegistry = new CircuitBreakerPackingPolicyRegistry(
            $circuitBreaker,
            $providerPolicy,
            $manualPolicy,
        );
        $packagingRepository = PackagingRepositoryFactory::create($entityManager);
        $calculationRepository = new DoctrinePackingCalculationRepository($entityManager);
        $calculateBoxSizeDecision = new CalculateBoxSizeDecisionMapper();
        $calculateBoxSize = new CalculateBoxSize(
            packingPolicyRegistry: $policyRegistry,
            logger: $logger,
        );
        $storePackingCalculation = new StorePackingCalculation(
            calculationRepository: $calculationRepository,
            logger: $logger,
        );
        $refreshPackingResult = new RefreshPackingResult(
            packagingRepository: $packagingRepository,
            calculateBoxSize: $calculateBoxSize,
            calculateBoxSizeDecision: $calculateBoxSizeDecision,
            storePackingCalculation: $storePackingCalculation,
            packingRefreshDifferenceSpecification: new PackingRefreshDifferenceSpecification(),
            logger: $logger,
        );

        return new FindBoxSize(
            refreshPolicy: new ManualResultsRequireRefreshPolicy(),
            packagingRepository: $packagingRepository,
            calculationRepository: $calculationRepository,
            commandMapper: new PackProductsCommandMapper(),
            requestHashBuilder: new RequestHashBuilder(),
            calculateBoxSize: $calculateBoxSize,
            calculateBoxSizeDecision: $calculateBoxSizeDecision,
            storePackingCalculation: $storePackingCalculation,
            refreshPackingResult: $refreshPackingResult,
            logger: $logger,
        );
    }
}
