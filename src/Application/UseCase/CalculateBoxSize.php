<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Mapper\PackProductsCommandMapper;
use App\Application\Packing\CalculateBoxSize as CalculateBoxSizeRunner;
use App\Application\Packing\CalculateBoxSizeDecisionMapper;
use App\Application\Packing\PackingRefreshDifferenceSpecification;
use App\Application\Packing\RefreshPackingResult;
use App\Application\Packing\StorePackingCalculation;
use App\Application\Service\RequestHashBuilder;
use App\Domain\Policy\Packing\PackingPolicyRegistry;
use App\Domain\Policy\Refresh\RequiresRefreshPolicy;
use App\Domain\Repository\PackagingRepository;
use App\Domain\Repository\PackingCalculationRepository;
use Psr\Log\LoggerInterface;

final class CalculateBoxSize extends FindBoxSize
{
    public function __construct(
        PackingPolicyRegistry $packingPolicyRegistry,
        RequiresRefreshPolicy $refreshPolicy,
        PackagingRepository $packagingRepository,
        PackingCalculationRepository $calculationRepository,
        PackProductsCommandMapper $commandMapper,
        RequestHashBuilder $requestHashBuilder,
        LoggerInterface $logger,
    ) {
        $calculateBoxSizeDecision = new CalculateBoxSizeDecisionMapper();
        $calculateBoxSize = new CalculateBoxSizeRunner(
            packingPolicyRegistry: $packingPolicyRegistry,
            logger: $logger,
        );
        $storePackingCalculation = new StorePackingCalculation(calculationRepository: $calculationRepository);

        parent::__construct(
            refreshPolicy: $refreshPolicy,
            packagingRepository: $packagingRepository,
            calculationRepository: $calculationRepository,
            commandMapper: $commandMapper,
            requestHashBuilder: $requestHashBuilder,
            calculateBoxSize: $calculateBoxSize,
            calculateBoxSizeDecision: $calculateBoxSizeDecision,
            storePackingCalculation: $storePackingCalculation,
            refreshPackingResult: new RefreshPackingResult(
                packagingRepository: $packagingRepository,
                calculateBoxSize: $calculateBoxSize,
                calculateBoxSizeDecision: $calculateBoxSizeDecision,
                storePackingCalculation: $storePackingCalculation,
                packingRefreshDifferenceSpecification: new PackingRefreshDifferenceSpecification(),
                logger: $logger,
            ),
            logger: $logger,
        );
    }
}
