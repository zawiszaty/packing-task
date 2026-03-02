<?php

declare(strict_types=1);

namespace App\Application\Mapper;

use App\Application\DTO\CalculationOutcome;
use App\Application\DTO\StoredBoxPayload;
use App\Application\DTO\StoredCalculationPayload;

final class StoredCalculationPayloadMapper
{
    public function encode(StoredCalculationPayload $payload): string
    {
        return json_encode([
            'outcome' => $payload->outcome,
            'reason' => $payload->reason,
            'message' => $payload->message,
            'box' => $payload->box === null ? null : [
                'id' => $payload->box->id,
                'width' => $payload->box->width,
                'height' => $payload->box->height,
                'length' => $payload->box->length,
                'maxWeight' => $payload->box->maxWeight,
            ],
        ], JSON_THROW_ON_ERROR);
    }

    public function decode(string $rawPayload): ?StoredCalculationPayload
    {
        $data = json_decode($rawPayload, true);
        if (!is_array($data)) {
            return null;
        }

        if (!is_string($data['outcome'] ?? null)) {
            return null;
        }

        $allowedOutcomes = array_map(
            static fn (CalculationOutcome $outcome): string => $outcome->value,
            CalculationOutcome::cases(),
        );
        if (!in_array($data['outcome'], $allowedOutcomes, true)) {
            return null;
        }

        $reason = $data['reason'] ?? null;
        if (!is_string($reason) && $reason !== null) {
            return null;
        }

        $message = $data['message'] ?? null;
        if (!is_string($message) && $message !== null) {
            return null;
        }

        $boxPayload = null;
        $rawBox = $data['box'] ?? null;
        if ($rawBox !== null) {
            if (!is_array($rawBox)) {
                return null;
            }

            if (!isset($rawBox['id'], $rawBox['width'], $rawBox['height'], $rawBox['length'], $rawBox['maxWeight'])) {
                return null;
            }

            if (!is_numeric($rawBox['id']) || !is_numeric($rawBox['width']) || !is_numeric($rawBox['height']) || !is_numeric($rawBox['length']) || !is_numeric($rawBox['maxWeight'])) {
                return null;
            }

            $boxPayload = new StoredBoxPayload(
                id: (int) $rawBox['id'],
                width: (float) $rawBox['width'],
                height: (float) $rawBox['height'],
                length: (float) $rawBox['length'],
                maxWeight: (float) $rawBox['maxWeight'],
            );
        }

        return new StoredCalculationPayload(
            outcome: $data['outcome'],
            reason: $reason,
            message: $message,
            box: $boxPayload,
        );
    }
}
