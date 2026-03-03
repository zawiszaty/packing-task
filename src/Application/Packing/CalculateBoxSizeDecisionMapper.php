<?php

declare(strict_types=1);

namespace App\Application\Packing;

use App\Application\DTO\CalculationOutcome;
use App\Application\DTO\PackingDecision;
use App\Application\DTO\SelectedBox;
use App\Application\DTO\StoredCalculationPayload;
use App\Domain\Entity\PackagingBox;

final class CalculateBoxSizeDecisionMapper
{
    private const NO_SINGLE_BOX_AVAILABLE_REASON = 'NO_SINGLE_BOX_AVAILABLE';
    private const MODEL_ERROR_REASON = 'MODEL_ERROR';
    private const NO_SINGLE_BOX_AVAILABLE_MESSAGE = 'Products cannot be packed into a single configured box.';
    private const MODEL_ERROR_MESSAGE = 'Cached result payload is invalid.';

    public function boxReturned(PackagingBox $box, string $source, string $requestHash): PackingDecision
    {
        return new PackingDecision(
            outcome: CalculationOutcome::BOX_RETURNED,
            box: new SelectedBox(
                id: $box->id,
                width: $box->width,
                height: $box->height,
                length: $box->length,
                maxWeight: $box->maxWeight,
            ),
            reason: null,
            source: $source,
            requestHash: $requestHash,
            message: null,
        );
    }

    public function noBoxReturned(string $source, string $requestHash): PackingDecision
    {
        return new PackingDecision(
            outcome: CalculationOutcome::NO_BOX_RETURNED,
            box: null,
            reason: self::NO_SINGLE_BOX_AVAILABLE_REASON,
            source: $source,
            requestHash: $requestHash,
            message: self::NO_SINGLE_BOX_AVAILABLE_MESSAGE,
        );
    }

    public function modelError(string $source, string $requestHash): PackingDecision
    {
        return new PackingDecision(
            outcome: CalculationOutcome::NO_BOX_RETURNED,
            box: null,
            reason: self::MODEL_ERROR_REASON,
            source: $source,
            requestHash: $requestHash,
            message: self::MODEL_ERROR_MESSAGE,
        );
    }

    public function fromStoredPayload(StoredCalculationPayload $storedPayload, string $source, string $requestHash): PackingDecision
    {
        try {
            $outcome = CalculationOutcome::from($storedPayload->outcome);
        } catch (\ValueError) {
            return $this->modelError(source: $source, requestHash: $requestHash);
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
            source: $source,
            requestHash: $requestHash,
            message: $storedPayload->message,
        );
    }
}
