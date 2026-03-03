<?php

declare(strict_types=1);

namespace App\Application\Service\Packing;

use App\Domain\Entity\PackagingBox;
use App\Domain\Policy\Packing\PackingPolicyRegistry;
use App\Domain\ValueObject\PackingRequest;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class CalculateBoxSize
{
    public function __construct(
        private readonly PackingPolicyRegistry $packingPolicyRegistry,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<PackagingBox> $boxes
     */
    public function calculate(PackingRequest $request, array $boxes, string $requestHash): CalculatedBoxSizeResult
    {
        $policy = $this->packingPolicyRegistry->resolve($request);

        /** @var array<string, true> $visitedSources */
        $visitedSources = [];

        while (true) {
            $policySource = $policy->source();
            $visitedSources[$policySource] = true;

            try {
                return new CalculatedBoxSizeResult(
                    selectedBox: $policy->pack($request, $boxes),
                    source: $policySource,
                );
            } catch (Throwable $exception) {
                $nextSource = $policy->failoverPolicySource();
                if ($nextSource === $policySource) {
                    throw $exception;
                }

                $nextPolicy = $this->packingPolicyRegistry->bySource($nextSource);
                if ($nextPolicy === null) {
                    throw $exception;
                }

                if (isset($visitedSources[$nextPolicy->source()])) {
                    throw new RuntimeException('Packing policy failover loop detected.', 0, $exception);
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
}
