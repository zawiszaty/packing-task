<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation;

use App\Application\Mapper\PackProductsCommandMapper;
use App\Application\Mapper\StoredCalculationPayloadMapper;
use App\Application\UseCase\CalculateBoxSize;
use App\Domain\Entity\PackagingBox;
use App\Domain\Policy\Refresh\ManualResultsRequireRefreshPolicy;
use App\Domain\Service\SimpleSmallestBoxSelector;
use App\Infrastructure\CircuitBreaker\Simple\StaticCircuitBreaker;
use App\Infrastructure\Factory\SerializerFactory;
use App\Infrastructure\Factory\ValidatorFactory;
use App\Infrastructure\Persistence\InMemory\InMemoryPackagingRepository;
use App\Infrastructure\Persistence\InMemory\InMemoryPackingCalculationRepository;
use App\Infrastructure\Policy\CircuitBreakerPackingPolicyRegistry;
use App\Infrastructure\Policy\ManualPackingPolicy;
use App\Infrastructure\Policy\ProviderPackingPolicy;
use App\Infrastructure\Provider\Stub\StubThreeDBinPackingClient;
use App\Presentation\Http\HttpApplication;
use App\Presentation\Http\SymfonyPackRequestResolver;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\Support\Fake\Presentation\Serializer\FailingDeserializeSerializer;

final class HttpApplicationTest extends TestCase
{
    public function testItReturns422WhenValidationFails(): void
    {
        $application = new HttpApplication(
            requestResolver: new SymfonyPackRequestResolver(
                serializer: SerializerFactory::create(),
                validator: ValidatorFactory::create(),
            ),
            calculateBoxSize: $this->buildUseCase(),
            serializer: SerializerFactory::create(),
        );

        $request = new Request(
            method: 'POST',
            uri: 'http://localhost/pack',
            headers: ['Content-Type' => 'application/json'],
            body: '{"products":[]}',
        );

        $response = $application->run($request);
        $payload = $this->decodePayload($response);

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('data', $payload);
        self::assertNull($payload['data'] ?? null);
        $firstError = $this->firstError($payload);

        self::assertSame('422', $firstError['status']);
        self::assertSame('Invalid request body', $firstError['title']);
    }

    public function testItReturns500WhenUnexpectedExceptionOccurs(): void
    {
        $application = new HttpApplication(
            requestResolver: new SymfonyPackRequestResolver(
                serializer: new FailingDeserializeSerializer(),
                validator: ValidatorFactory::create(),
            ),
            calculateBoxSize: $this->buildUseCase(),
            serializer: SerializerFactory::create(),
        );

        $request = new Request(
            method: 'POST',
            uri: 'http://localhost/pack',
            headers: ['Content-Type' => 'application/json'],
            body: '{"products":[{"width":1,"height":1,"length":1,"weight":1}]}',
        );

        $response = $application->run($request);
        $payload = $this->decodePayload($response);

        self::assertSame(500, $response->getStatusCode());
        self::assertArrayHasKey('data', $payload);
        self::assertNull($payload['data'] ?? null);
        $firstError = $this->firstError($payload);

        self::assertSame('500', $firstError['status']);
        self::assertSame('INTERNAL_ERROR', $firstError['code']);
    }

    private function buildUseCase(): CalculateBoxSize
    {
        $circuitBreaker = new StaticCircuitBreaker(available: false);
        $providerPolicy = new ProviderPackingPolicy(
            providerClient: new StubThreeDBinPackingClient(selectedBoxId: 1),
            circuitBreaker: $circuitBreaker,
        );
        $manualPolicy = new ManualPackingPolicy(selector: new SimpleSmallestBoxSelector());
        $registry = new CircuitBreakerPackingPolicyRegistry(
            circuitBreaker: $circuitBreaker,
            providerPolicy: $providerPolicy,
            manualPolicy: $manualPolicy,
        );

        return new CalculateBoxSize(
            packingPolicyRegistry: $registry,
            refreshPolicy: new ManualResultsRequireRefreshPolicy(),
            packagingRepository: new InMemoryPackagingRepository(boxes: [
                new PackagingBox(id: 1, width: 3.0, height: 3.0, length: 3.0, maxWeight: 20.0),
            ]),
            calculationRepository: new InMemoryPackingCalculationRepository(),
            commandMapper: new PackProductsCommandMapper(),
            storedPayloadMapper: new StoredCalculationPayloadMapper(),
            requestHashBuilder: new \App\Application\Service\RequestHashBuilder(),
            logger: new NullLogger(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(\Psr\Http\Message\ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: string, code: string, title: string}
     */
    private function firstError(array $payload): array
    {
        $errors = $payload['errors'] ?? null;
        self::assertIsArray($errors);

        $first = $errors[0] ?? null;
        self::assertIsArray($first);

        $status = $first['status'] ?? null;
        $code = $first['code'] ?? null;
        $title = $first['title'] ?? null;

        self::assertIsString($status);
        self::assertIsString($code);
        self::assertIsString($title);

        return [
            'status' => $status,
            'code' => $code,
            'title' => $title,
        ];
    }
}
