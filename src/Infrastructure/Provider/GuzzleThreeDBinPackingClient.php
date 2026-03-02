<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use App\Domain\ValueObject\PackingRequest;
use App\Infrastructure\Provider\Model\PackApiResponse;
use App\Infrastructure\Provider\Model\PackResult;
use App\Infrastructure\Provider\Payload\PackIntoManyRequestPayload;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class GuzzleThreeDBinPackingClient implements ThreeDBinPackingClient
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $logger,
        private readonly string $username,
        private readonly string $apiKey,
        private readonly string $endpoint = '/packer/packIntoMany',
    ) {
    }

    public function pack(PackingRequest $request, array $boxes): PackResult
    {
        $payload = PackIntoManyRequestPayload::fromDomain($this->username, $this->apiKey, $request, $boxes);
        $response = $this->httpClient->request('POST', $this->endpoint, [
            'json' => $payload->toArray(),
        ]);

        try {
            $decoded = $this->serializer->deserialize(
                data: (string) $response->getBody(),
                type: PackApiResponse::class,
                format: 'json',
            );
        } catch (ExceptionInterface $exception) {
            throw new \RuntimeException('Invalid JSON response from 3DBinPacking API.');
        }

        if (!$decoded instanceof PackApiResponse) {
            throw new \RuntimeException('Invalid JSON response from 3DBinPacking API.');
        }

        return $this->toPackResult($decoded);
    }

    private function toPackResult(PackApiResponse $response): PackResult
    {
        $data = $response->response;
        if ($data === null) {
            return new PackResult(null);
        }

        $binsPacked = $data->bins_packed;
        $unpackedItems = $data->not_packed_items ?? [];
        $errors = $data->errors ?? [];

        if ($data->status !== 1) {
            $errorMessages = [];
            foreach ($errors as $error) {
                if ($error->message !== null && $error->message !== '') {
                    $errorMessages[] = $error->message;
                }
            }

            $this->logger->error('provider.3dbinpacking.invalid_status', [
                'responseId' => $data->id,
                'status' => $data->status,
                'errors' => $errorMessages,
            ]);

            $details = $errorMessages !== [] ? ' Errors: ' . implode(' | ', $errorMessages) : '';
            throw new \RuntimeException('3DBinPacking API returned non-success status.' . $details);
        }

        if ($errors !== []) {
            $errorMessages = [];
            foreach ($errors as $error) {
                if ($error->message !== null && $error->message !== '') {
                    $errorMessages[] = $error->message;
                }
            }

            $this->logger->error('provider.3dbinpacking.api_errors', [
                'responseId' => $data->id,
                'status' => $data->status,
                'errors' => $errorMessages,
            ]);

            $details = $errorMessages !== [] ? ' Errors: ' . implode(' | ', $errorMessages) : '';
            throw new \RuntimeException('3DBinPacking API returned errors.' . $details);
        }

        if (count($unpackedItems) > 0 || count($binsPacked) !== 1) {
            return new PackResult(null);
        }

        $binId = $binsPacked[0]->bin_data?->id;
        if (is_int($binId)) {
            return new PackResult($binId);
        }

        if (is_string($binId) && ctype_digit($binId)) {
            return new PackResult((int) $binId);
        }

        return new PackResult(null);
    }
}
