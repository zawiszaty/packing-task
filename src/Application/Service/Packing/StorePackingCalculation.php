<?php

declare(strict_types=1);

namespace App\Application\Service\Packing;

use App\Application\DTO\PackingDecision;
use App\Domain\Entity\PackingCalculation;
use App\Domain\Repository\PackingCalculationRepository;
use App\Domain\ValueObject\PackingRequest;
use Psr\Log\LoggerInterface;

final class StorePackingCalculation
{
    public function __construct(
        private readonly PackingCalculationRepository $calculationRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function store(PackingRequest $request, PackingDecision $decision, bool $refreshed = false): void
    {
        $normalizedResult = json_encode($this->normalizeResult($decision), JSON_THROW_ON_ERROR);
        $this->logger->info('packing.calculation_normalized_result_prepared', [
            'requestHash' => $decision->requestHash,
            'source' => $decision->source,
            'selectedBoxId' => $decision->box?->id,
            'normalizedResult' => $normalizedResult,
        ]);

        $entity = new PackingCalculation(
            id: 0,
            inputHash: $decision->requestHash,
            normalizedRequest: json_encode($this->normalizeRequest($request), JSON_THROW_ON_ERROR),
            normalizedResult: $normalizedResult,
            selectedBoxId: $decision->box?->id,
            providerSource: $decision->source,
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

    /**
     * @return array{
     *     outcome: string,
     *     reason: ?string,
     *     message: ?string,
     *     box: ?array{id: int, width: float, height: float, length: float, maxWeight: float}
     * }
     */
    private function normalizeResult(PackingDecision $decision): array
    {
        return [
            'outcome' => $decision->outcome->value,
            'reason' => $decision->reason,
            'message' => $decision->message,
            'box' => $decision->box === null ? null : [
                'id' => $decision->box->id,
                'width' => $decision->box->width,
                'height' => $decision->box->height,
                'length' => $decision->box->length,
                'maxWeight' => $decision->box->maxWeight,
            ],
        ];
    }
}
