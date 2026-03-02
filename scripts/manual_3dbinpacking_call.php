<?php

declare(strict_types=1);

use App\Domain\Entity\PackagingBox;
use App\Domain\ValueObject\Dimensions;
use App\Domain\ValueObject\PackingRequest;
use App\Domain\ValueObject\ProductToPack;
use App\Domain\ValueObject\Weight;
use App\Infrastructure\Factory\DotenvFactory;
use App\Infrastructure\Factory\SerializerFactory;
use App\Infrastructure\Provider\GuzzleThreeDBinPackingClient;
use App\Infrastructure\Provider\Model\PackApiResponse;
use App\Infrastructure\Provider\Payload\PackIntoManyRequestPayload;
use GuzzleHttp\Client;
use Psr\Log\NullLogger;

require __DIR__ . '/../vendor/autoload.php';

$projectDir = dirname(__DIR__);
DotenvFactory::boot($projectDir);

$username = DotenvFactory::get('THREEDBP_USERNAME');
$apiKey = DotenvFactory::get('THREEDBP_API_KEY');
$baseUri = DotenvFactory::get('THREEDBP_BASE_URI') ?? 'https://global-api.3dbinpacking.com';
$endpoint = DotenvFactory::get('THREEDBP_ENDPOINT') ?? '/packer/packIntoMany';

if ($username === null || $apiKey === null) {
    fwrite(STDERR, "Missing env credentials: THREEDBP_USERNAME / THREEDBP_API_KEY\n");
    exit(1);
}

$options = getopt('', ['input::', 'boxes::', 'help']);
if (isset($options['help'])) {
    echo "Usage:\n";
    echo "  php scripts/manual_3dbinpacking_call.php [--input=path/to/input.json] [--boxes=path/to/boxes.json]\n\n";
    echo "Default input: sample.json from project root\n";
    echo "--input JSON format: {\"products\":[{\"width\":1,\"height\":1,\"length\":1,\"weight\":1}]}\n";
    echo "--boxes JSON format: [{\"id\":1,\"width\":2,\"height\":2,\"length\":2,\"maxWeight\":10}]\n";
    exit(0);
}

[$request, $boxes] = loadInputData(
    inputPath: isset($options['input']) && is_string($options['input']) ? $options['input'] : $projectDir . '/sample.json',
    boxesPath: isset($options['boxes']) && is_string($options['boxes']) ? $options['boxes'] : null,
);
$resolvedInputPath = isset($options['input']) && is_string($options['input']) ? $options['input'] : $projectDir . '/sample.json';
$resolvedBoxesPath = isset($options['boxes']) && is_string($options['boxes']) ? $options['boxes'] : null;

$payload = PackIntoManyRequestPayload::fromDomain(
    username: $username,
    apiKey: $apiKey,
    request: $request,
    boxes: $boxes,
)->toArray();

$http = new Client(['base_uri' => $baseUri]);
$serializer = SerializerFactory::create();

echo "=== Request Config ===\n";
echo "Base URI: {$baseUri}\n";
echo "Endpoint: {$endpoint}\n";
echo "Input file: {$resolvedInputPath}\n";
echo "Boxes file: " . ($resolvedBoxesPath ?? 'built-in defaults') . "\n";
echo "Products: " . count($request->products) . "\n";
echo "Boxes: " . count($boxes) . "\n\n";

echo "=== Sent Payload ===\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

try {
    $response = $http->request('POST', $endpoint, ['json' => $payload]);
    $rawBody = (string) $response->getBody();

    echo "=== Raw Response ({$response->getStatusCode()}) ===\n";
    echo $rawBody . "\n\n";

    $decoded = $serializer->deserialize(
        data: $rawBody,
        type: PackApiResponse::class,
        format: 'json',
    );

    if ($decoded instanceof PackApiResponse) {
        $data = $decoded->response;
        $errors = $data?->errors ?? [];
        $notPacked = $data?->not_packed_items ?? [];
        $bins = $data?->bins_packed ?? [];
        $binId = $bins[0]->bin_data?->id ?? null;

        echo "=== Parsed (serializer) ===\n";
        echo 'status: ' . (string) ($data?->status ?? 'null') . "\n";
        echo 'errors_count: ' . count($errors) . "\n";
        echo 'not_packed_count: ' . count($notPacked) . "\n";
        echo 'bins_packed_count: ' . count($bins) . "\n";
        echo 'first_bin_data_id: ' . (is_scalar($binId) ? (string) $binId : 'null') . "\n\n";
    }

    $client = new GuzzleThreeDBinPackingClient(
        httpClient: $http,
        serializer: $serializer,
        logger: new NullLogger(),
        username: $username,
        apiKey: $apiKey,
        endpoint: $endpoint,
    );
    $packResult = $client->pack(request: $request, boxes: $boxes);

    echo "=== Client Result ===\n";
    echo 'selectedBoxId: ' . ($packResult->selectedBoxId() === null ? 'null' : (string) $packResult->selectedBoxId()) . "\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Call failed: " . $exception::class . ": " . $exception->getMessage() . "\n");
    exit(1);
}

/**
 * @return array{PackingRequest, list<PackagingBox>}
 */
function loadInputData(?string $inputPath, ?string $boxesPath): array
{
    $productsData = loadProducts($inputPath);
    $boxesData = loadBoxes($boxesPath);

    $products = [];
    foreach ($productsData as $product) {
        $products[] = new ProductToPack(
            dimensions: new Dimensions(
                width: (float) ($product['width'] ?? 0),
                height: (float) ($product['height'] ?? 0),
                length: (float) ($product['length'] ?? 0),
            ),
            weight: new Weight(valueKg: (float) ($product['weight'] ?? 0)),
        );
    }

    $boxes = [];
    foreach ($boxesData as $row) {
        $boxes[] = new PackagingBox(
            id: (int) ($row['id'] ?? 0),
            width: (float) ($row['width'] ?? 0),
            height: (float) ($row['height'] ?? 0),
            length: (float) ($row['length'] ?? 0),
            maxWeight: (float) ($row['maxWeight'] ?? 0),
        );
    }

    return [new PackingRequest(products: $products), $boxes];
}

/**
 * @return list<array<string, int|float|string>>
 */
function loadProducts(?string $inputPath): array
{
    if ($inputPath !== null) {
        $raw = file_get_contents($inputPath);
        if ($raw === false) {
            throw new RuntimeException("Cannot read input file: {$inputPath}");
        }

        /** @var mixed $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !isset($decoded['products']) || !is_array($decoded['products'])) {
            throw new RuntimeException("Invalid --input JSON format. Expected {\"products\":[...]}.");
        }

        /** @var list<array<string, int|float|string>> $products */
        $products = array_values(array_filter($decoded['products'], 'is_array'));

        return $products;
    }

    return [
        ['id' => 'Speakers-1', 'width' => 3, 'height' => 3, 'length' => 3, 'weight' => 2],
        ['id' => 'Speakers-2', 'width' => 3, 'height' => 3, 'length' => 3, 'weight' => 2],
        ['id' => 'Bigger item', 'width' => 3, 'height' => 3, 'length' => 5, 'weight' => 1],
        ['id' => 'Too big item', 'width' => 5, 'height' => 5, 'length' => 5, 'weight' => 1],
    ];
}

/**
 * @return list<array<string, int|float>>
 */
function loadBoxes(?string $boxesPath): array
{
    if ($boxesPath !== null) {
        $raw = file_get_contents($boxesPath);
        if ($raw === false) {
            throw new RuntimeException("Cannot read boxes file: {$boxesPath}");
        }

        /** @var mixed $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid --boxes JSON format. Expected array of boxes.');
        }

        /** @var list<array<string, int|float>> $boxes */
        $boxes = array_values(array_filter($decoded, 'is_array'));

        return $boxes;
    }

    return [
        ['id' => 1, 'width' => 2.5, 'height' => 3.0, 'length' => 1.0, 'maxWeight' => 20.0],
        ['id' => 2, 'width' => 4.0, 'height' => 4.0, 'length' => 4.0, 'maxWeight' => 20.0],
        ['id' => 3, 'width' => 2.0, 'height' => 2.0, 'length' => 10.0, 'maxWeight' => 20.0],
        ['id' => 4, 'width' => 5.5, 'height' => 6.0, 'length' => 7.5, 'maxWeight' => 30.0],
        ['id' => 5, 'width' => 9.0, 'height' => 9.0, 'length' => 9.0, 'maxWeight' => 30.0],
    ];
}
