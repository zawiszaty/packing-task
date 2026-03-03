<?php

declare(strict_types=1);

namespace App\Application\Packing;

final class PackingRefreshDifferenceSpecification
{
    public function compare(?int $previousSelectedBoxId, ?int $refreshedSelectedBoxId): PackingResultDifference
    {
        if ($previousSelectedBoxId === $refreshedSelectedBoxId) {
            return PackingResultDifference::UNCHANGED;
        }

        if ($previousSelectedBoxId === null && $refreshedSelectedBoxId !== null) {
            return PackingResultDifference::IMPROVED;
        }

        if ($previousSelectedBoxId !== null && $refreshedSelectedBoxId === null) {
            return PackingResultDifference::REGRESSED;
        }

        return PackingResultDifference::CHANGED;
    }
}
