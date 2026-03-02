<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\CalculationOutcome;
use App\Application\DTO\PackingDecision;
use App\Application\DTO\PackProductsCommand;
use App\Application\DTO\SelectedBox;
use App\Application\DTO\StoredBoxPayload;
use App\Application\DTO\StoredCalculationPayload;
use App\Application\Mapper\PackProductsCommandMapper;
use App\Application\Mapper\StoredCalculationPayloadMapper;
use App\Application\Service\RequestHashBuilder;
use App\Domain\Entity\PackagingBox;
use App\Domain\Entity\PackingCalculation;
use App\Domain\Policy\Packing\PackingPolicyRegistry;
use App\Domain\Policy\Refresh\RequiresRefreshPolicy;
use App\Domain\Repository\PackagingRepository;
use App\Domain\Repository\PackingCalculationRepository;
use App\Domain\ValueObject\PackingRequest;
use Psr\Log\LoggerInterface;

final class CalculateBoxSize
{
    public function __construct(
        private readonly PackingPolicyRegistry $packingPolicyRegistry,
        private readonly RequiresRefreshPolicy $refreshPolicy,
        private readonly PackagingRepository $packagingRepository,
        private readonly PackingCalculationRepository $calculationRepository,
        private readonly PackProductsCommandMapper $commandMapper,
        private readonly StoredCalculationPayloadMapper $storedPayloadMapper,
        private readonly RequestHashBuilder $requestHashBuilder,
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
                $this->refresh($request, $latestCalculation);
            }

            return $this->hydrateResultFromStoredCalculation($latestCalculation, $requestHash);
        }

        return $this->calculate($request, $requestHash);
    }

    private function calculate(PackingRequest $request, string $requestHash): PackingDecision
    {
        $boxes = $this->packagingRepository->findAll();
        [$selectedBox, $policy] = $this->packWithFailover(
            request: $request,
            boxes: $boxes,
            requestHash: $requestHash,
        );

        if ($selectedBox === null) {
            $result = new PackingDecision(
                outcome: CalculationOutcome::NO_BOX_RETURNED,
                box: null,
                reason: 'NO_SINGLE_BOX_AVAILABLE',
                source: $policy->source(),
                requestHash: $requestHash,
                message: 'Products cannot be packed into a single configured box.',
            );
            $this->persistCalculation($request, $result, $policy->source(), $requestHash);
            $this->logger->info('packing.no_box_returned', [
                'requestHash' => $requestHash,
                'source' => $policy->source(),
            ]);

            return $result;
        }

        $result = new PackingDecision(
            outcome: CalculationOutcome::BOX_RETURNED,
            box: $this->toSelectedBox($selectedBox),
            reason: null,
            source: $policy->source(),
            requestHash: $requestHash,
            message: null,
        );
        $this->persistCalculation($request, $result, $policy->source(), $requestHash);
        $this->logger->info('packing.box_returned', [
            'requestHash' => $requestHash,
            'source' => $policy->source(),
            'boxId' => $result->box?->id,
        ]);

        return $result;
    }

    private function refresh(PackingRequest $request, PackingCalculation $latestCalculation): void
    {
        $requestHash = $latestCalculation->inputHash;
        $previousSelectedBoxId = $this->resolveStoredSelectedBoxId($latestCalculation);

        try {
            $boxes = $this->packagingRepository->findAll();
            [$selectedBox, $policy] = $this->packWithFailover(
                request: $request,
                boxes: $boxes,
                requestHash: $requestHash,
            );

            $mapped = $selectedBox === null
                ? new PackingDecision(
                    outcome: CalculationOutcome::NO_BOX_RETURNED,
                    box: null,
                    reason: 'NO_SINGLE_BOX_AVAILABLE',
                    source: $policy->source(),
                    requestHash: $latestCalculation->inputHash,
                    message: 'Products cannot be packed into a single configured box.',
                )
                : new PackingDecision(
                    outcome: CalculationOutcome::BOX_RETURNED,
                    box: $this->toSelectedBox($selectedBox),
                    reason: null,
                    source: $policy->source(),
                    requestHash: $latestCalculation->inputHash,
                );

            $this->checkRefreshResultDifference(
                requestHash: $requestHash,
                previousSource: $latestCalculation->providerSource,
                previousSelectedBoxId: $previousSelectedBoxId,
                refreshedSource: $mapped->source,
                refreshedSelectedBoxId: $mapped->box?->id,
            );

            $this->persistCalculation(
                request: $request,
                result: $mapped,
                providerSource: $policy->source(),
                requestHash: $latestCalculation->inputHash,
                refreshed: true,
            );
        } catch (\Throwable $exception) {
            $this->logger->error('packing.refresh_failed', [
                'requestHash' => $requestHash,
                'exceptionClass' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            // Keep serving stored value.
        }
    }

    private function resolveStoredSelectedBoxId(PackingCalculation $calculation): ?int
    {
        $storedPayload = $this->storedPayloadMapper->decode($calculation->normalizedResult);
        if ($storedPayload?->box !== null) {
            return $storedPayload->box->id;
        }

        return $calculation->selectedBoxId;
    }

    private function checkRefreshResultDifference(
        string $requestHash,
        string $previousSource,
        ?int $previousSelectedBoxId,
        string $refreshedSource,
        ?int $refreshedSelectedBoxId,
    ): void {
        if ($previousSelectedBoxId === $refreshedSelectedBoxId) {
            return;
        }

        $context = [
            'requestHash' => $requestHash,
            'previousSource' => $previousSource,
            'previousSelectedBoxId' => $previousSelectedBoxId,
            'refreshedSource' => $refreshedSource,
            'refreshedSelectedBoxId' => $refreshedSelectedBoxId,
        ];

        if ($previousSelectedBoxId === null && $refreshedSelectedBoxId !== null) {
            $this->logger->info('packing.refresh_result_changed', $context);
            return;
        }

        if ($previousSelectedBoxId !== null && $refreshedSelectedBoxId === null) {
            $this->logger->error('packing.refresh_result_changed', $context);
            return;
        }

        $this->logger->info('packing.refresh_result_changed', $context);
    }

    /**
     * @param list<PackagingBox> $boxes
     * @return array{0: ?PackagingBox, 1: \App\Domain\Policy\Packing\PackingPolicy}
     */
    private function packWithFailover(
        PackingRequest $request,
        array $boxes,
        string $requestHash,
    ): array {
        $policy = $this->packingPolicyRegistry->resolve($request);

        /** @var array<string, true> $visitedSources */
        $visitedSources = [];

        while (true) {
            $policySource = $policy->source();
            $visitedSources[$policySource] = true;

            try {
                return [$policy->pack($request, $boxes), $policy];
            } catch (\Throwable $exception) {
                $nextSource = $policy->failoverPolicySource();
                if ($nextSource === $policySource) {
                    throw $exception;
                }

                $nextPolicy = $this->packingPolicyRegistry->bySource($nextSource);
                if ($nextPolicy === null) {
                    throw $exception;
                }

                if (isset($visitedSources[$nextPolicy->source()])) {
                    throw new \RuntimeException('Packing policy failover loop detected.', 0, $exception);
                }

                $this->logger->error('packing.policy_failed_using_failover', [
                    'requestHash' => $requestHash,
                    'policy' => $policySource,
                    'failoverPolicy' => $nextPolicy->source(),
                ]);

                $policy = $nextPolicy;
            }
        }
    }

    private function hydrateResultFromStoredCalculation(PackingCalculation $calculation, string $requestHash): PackingDecision
    {
        $storedPayload = $this->storedPayloadMapper->decode($calculation->normalizedResult);
        if ($storedPayload === null) {
            return new PackingDecision(
                outcome: CalculationOutcome::NO_BOX_RETURNED,
                box: null,
                reason: 'MODEL_ERROR',
                source: $calculation->providerSource,
                requestHash: $requestHash,
                message: 'Cached result payload is invalid.',
            );
        }

        try {
            $outcome = CalculationOutcome::from($storedPayload->outcome);
        } catch (\ValueError) {
            return new PackingDecision(
                outcome: CalculationOutcome::NO_BOX_RETURNED,
                box: null,
                reason: 'MODEL_ERROR',
                source: $calculation->providerSource,
                requestHash: $requestHash,
                message: 'Cached result payload is invalid.',
            );
        }

        return new PackingDecision(
            outcome: $outcome,
            box: $storedPayload->box === null ? null : new SelectedBox(
                id: $storedPayload->box->id,
                width: $storedPayload->box->width,
                height: $storedPayload->box->height,
                length: $storedPayload->box->length,
                maxWeight: $storedPayload->box->maxWeight,
            ),
            reason: $storedPayload->reason,
            source: $calculation->providerSource,
            requestHash: $requestHash,
            message: $storedPayload->message,
        );
    }

    private function persistCalculation(
        PackingRequest $request,
        PackingDecision $result,
        string $providerSource,
        string $requestHash,
        bool $refreshed = false,
    ): void {
        $payload = $this->storedPayloadMapper->encode(
            new StoredCalculationPayload(
                outcome: $result->outcome->value,
                reason: $result->reason,
                message: $result->message,
                box: $result->box === null ? null : new StoredBoxPayload(
                    id: $result->box->id,
                    width: $result->box->width,
                    height: $result->box->height,
                    length: $result->box->length,
                    maxWeight: $result->box->maxWeight,
                ),
            ),
        );

        $entity = new PackingCalculation(
            id: 0,
            inputHash: $requestHash,
            normalizedRequest: json_encode($this->normalizeRequest($request), JSON_THROW_ON_ERROR),
            normalizedResult: $payload,
            selectedBoxId: $result->box?->id,
            providerSource: $providerSource,
            createdAt: new \DateTimeImmutable(),
            refreshedAt: $refreshed ? new \DateTimeImmutable() : null,
        );

        $this->calculationRepository->save($entity);
    }

    /**
     * @return list<array{width: float, height: float, length: float, weight: float}>
     */
    private function normalizeRequest(PackingRequest $request): array
    {
        return array_map(
            static fn ($product): array => [
                'width' => $product->dimensions->width,
                'height' => $product->dimensions->height,
                'length' => $product->dimensions->length,
                'weight' => $product->weight->valueKg,
            ],
            $request->products,
        );
    }

    private function toSelectedBox(PackagingBox $box): SelectedBox
    {
        return new SelectedBox(
            id: $box->id,
            width: $box->width,
            height: $box->height,
            length: $box->length,
            maxWeight: $box->maxWeight,
        );
    }
}
