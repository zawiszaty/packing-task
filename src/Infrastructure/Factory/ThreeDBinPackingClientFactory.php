<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Infrastructure\Provider\DisabledThreeDBinPackingClient;
use App\Infrastructure\Provider\GuzzleThreeDBinPackingClient;
use App\Infrastructure\Provider\ThreeDBinPackingClient;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

final class ThreeDBinPackingClientFactory
{
    public static function create(string $projectDir, LoggerInterface $logger): ThreeDBinPackingClient
    {
        DotenvFactory::boot($projectDir);

        $username = DotenvFactory::get('THREEDBP_USERNAME');
        $apiKey = DotenvFactory::get('THREEDBP_API_KEY');

        if ($username === null || $apiKey === null) {
            return new DisabledThreeDBinPackingClient('3DBinPacking credentials are missing.');
        }

        $baseUri = DotenvFactory::get('THREEDBP_BASE_URI') ?? 'https://global-api.3dbinpacking.com';
        $endpoint = DotenvFactory::get('THREEDBP_ENDPOINT') ?? '/packer/packIntoMany';

        return new GuzzleThreeDBinPackingClient(
            httpClient: new Client(['base_uri' => $baseUri]),
            serializer: SerializerFactory::create(),
            logger: $logger,
            username: $username,
            apiKey: $apiKey,
            endpoint: $endpoint,
        );
    }
}
