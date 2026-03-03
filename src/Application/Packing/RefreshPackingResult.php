<?php

declare(strict_types=1);

namespace App\Application\Packing;

use App\Domain\Entity\PackingCalculation;
use App\Domain\Repository\PackagingRepository;
use App\Domain\ValueObject\PackingRequest;
use Psr\Log\LoggerInterface;

final class RefreshPackingResult
{
    public function __construct(
        private readonly PackagingRepository $packagingRepository,
        private readonly CalculateBoxSize $calculateBoxSize,
        private readonly CalculateBoxSizeDecisionMapper $calculateBoxSizeDecision,
        private readonly StorePackingCalculation $storePackingCalculation,
        private readonly PackingRefreshDifferenceSpecification $packingRefreshDifferenceSpecification,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function refresh(PackingRequest $request, PackingCalculation $latestCalculation): void
    {
        $requestHash = $latestCalculation->inputHash;

        try {
            $previousSelectedBoxId = $latestCalculation->selectedBoxId;
            $boxes = $this->packagingRepository->findAll();
            $calculatedBoxSizeResult = $this->calculateBoxSize->calculate(
                request: $request,
                boxes: $boxes,
                requestHash: $requestHash,
            );

            $refreshedDecision = $calculatedBoxSizeResult->selectedBox === null
                ? $this->calculateBoxSizeDecision->noBoxReturned(
                    source: $calculatedBoxSizeResult->source,
                    requestHash: $requestHash,
                )
                : $this->calculateBoxSizeDecision->boxReturned(
                    box: $calculatedBoxSizeResult->selectedBox,
                    source: $calculatedBoxSizeResult->source,
                    requestHash: $requestHash,
                );

            $difference = $this->packingRefreshDifferenceSpecification->compare(
                previousSelectedBoxId: $previousSelectedBoxId,
                refreshedSelectedBoxId: $refreshedDecision->box?->id,
            );
            if ($difference !== PackingResultDifference::UNCHANGED) {
                $context = [
                    'requestHash' => $requestHash,
                    'previousSource' => $latestCalculation->providerSource,
                    'previousSelectedBoxId' => $previousSelectedBoxId,
                    'refreshedSource' => $refreshedDecision->source,
                    'refreshedSelectedBoxId' => $refreshedDecision->box?->id,
                    'difference' => $difference->value,
                ];

                if ($difference === PackingResultDifference::REGRESSED) {
                    $this->logger->error('packing.refresh_result_changed', $context);
                } else {
                    $this->logger->info('packing.refresh_result_changed', $context);
                }
            }

            $this->storePackingCalculation->store(
                request: $request,
                decision: $refreshedDecision,
                refreshed: true,
            );
        } catch (\Throwable $exception) {
            $this->logger->error('packing.refresh_failed', [
                'requestHash' => $requestHash,
                'exceptionClass' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
