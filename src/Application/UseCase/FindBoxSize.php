<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\PackingDecision;
use App\Application\DTO\PackProductsCommand;
use App\Application\Mapper\PackProductsCommandMapper;
use App\Application\Packing\CalculateBoxSize;
use App\Application\Packing\CalculateBoxSizeDecisionMapper;
use App\Application\Packing\RefreshPackingResult;
use App\Application\Packing\StorePackingCalculation;
use App\Application\Service\RequestHashBuilder;
use App\Domain\Entity\PackingCalculation;
use App\Domain\Policy\Refresh\RequiresRefreshPolicy;
use App\Domain\Repository\PackagingRepository;
use App\Domain\Repository\PackingCalculationRepository;
use Psr\Log\LoggerInterface;

class FindBoxSize
{
    public function __construct(
        private readonly RequiresRefreshPolicy $refreshPolicy,
        private readonly PackagingRepository $packagingRepository,
        private readonly PackingCalculationRepository $calculationRepository,
        private readonly PackProductsCommandMapper $commandMapper,
        private readonly RequestHashBuilder $requestHashBuilder,
        private readonly CalculateBoxSize $calculateBoxSize,
        private readonly CalculateBoxSizeDecisionMapper $calculateBoxSizeDecision,
        private readonly StorePackingCalculation $storePackingCalculation,
        private readonly RefreshPackingResult $refreshPackingResult,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(PackProductsCommand $command): PackingDecision
    {
        $request = $this->commandMapper->toPackingRequest($command);
        $requestHash = $this->requestHashBuilder->fromProducts($command->products);
        $latestCalculation = $this->calculationRepository->findLatestByInputHash($requestHash);

        if ($latestCalculation !== null) {
            if ($latestCalculation->requiresRefresh($this->refreshPolicy)) {
                $this->refreshPackingResult->refresh(
                    request: $request,
                    latestCalculation: $latestCalculation,
                );
            }

            return $this->fromStoredCalculation(
                calculation: $latestCalculation,
                requestHash: $requestHash,
            );
        }

        $boxes = $this->packagingRepository->findAll();
        $calculatedBoxSizeResult = $this->calculateBoxSize->calculate(
            request: $request,
            boxes: $boxes,
            requestHash: $requestHash,
        );

        $decision = $calculatedBoxSizeResult->selectedBox === null
            ? $this->calculateBoxSizeDecision->noBoxReturned(
                source: $calculatedBoxSizeResult->source,
                requestHash: $requestHash,
            )
            : $this->calculateBoxSizeDecision->boxReturned(
                box: $calculatedBoxSizeResult->selectedBox,
                source: $calculatedBoxSizeResult->source,
                requestHash: $requestHash,
            );

        $this->storePackingCalculation->store(
            request: $request,
            decision: $decision,
        );

        if ($decision->box === null) {
            $this->logger->info('packing.no_box_returned', [
                'requestHash' => $requestHash,
                'source' => $decision->source,
            ]);
        } else {
            $this->logger->info('packing.box_returned', [
                'requestHash' => $requestHash,
                'source' => $decision->source,
                'boxId' => $decision->box->id,
            ]);
        }

        return $decision;
    }

    private function fromStoredCalculation(PackingCalculation $calculation, string $requestHash): PackingDecision
    {
        if ($calculation->selectedBoxId === null) {
            return $this->calculateBoxSizeDecision->noBoxReturned(
                source: $calculation->providerSource,
                requestHash: $requestHash,
            );
        }

        $selectedBox = $this->packagingRepository->findById($calculation->selectedBoxId);
        if ($selectedBox === null) {
            return $this->calculateBoxSizeDecision->modelError(
                source: $calculation->providerSource,
                requestHash: $requestHash,
            );
        }

        return $this->calculateBoxSizeDecision->boxReturned(
            box: $selectedBox,
            source: $calculation->providerSource,
            requestHash: $requestHash,
        );
    }
}
