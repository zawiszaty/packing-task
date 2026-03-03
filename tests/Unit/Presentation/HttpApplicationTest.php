<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation;

use App\Application\Mapper\PackProductsCommandMapper;
use App\Application\Packing\CalculateBoxSize as CalculateBoxSizeRunner;
use App\Application\Packing\CalculateBoxSizeDecisionMapper;
use App\Application\Packing\PackingRefreshDifferenceSpecification;
use App\Application\Packing\RefreshPackingResult;
use App\Application\Packing\StorePackingCalculation;
use App\Application\DTO\PackProductsCommand;
use App\Application\UseCase\FindBoxSize;
use App\Domain\Entity\PackagingBox;
use App\Domain\Policy\Refresh\ManualResultsRequireRefreshPolicy;
use App\Domain\Service\SimpleSmallestBoxSelector;
use App\Infrastructure\CircuitBreaker\Simple\StaticCircuitBreaker;
use App\Infrastructure\Factory\SerializerFactory;
use App\Infrastructure\Factory\ValidatorFactory;
use Tests\Support\Fake\Infrastructure\Persistence\InMemoryPackagingRepository;
use Tests\Support\Fake\Infrastructure\Persistence\InMemoryPackingCalculationRepository;
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
            findBoxSize: $this->buildUseCase(),
            serializer: SerializerFactory::create(),
            logger: new NullLogger(),
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
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
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
            findBoxSize: $this->buildUseCase(),
            serializer: SerializerFactory::create(),
            logger: new NullLogger(),
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
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertArrayHasKey('data', $payload);
        self::assertNull($payload['data'] ?? null);
        $firstError = $this->firstError($payload);

        self::assertSame('500', $firstError['status']);
        self::assertSame('INTERNAL_ERROR', $firstError['code']);
        self::assertSame('An unexpected error occurred.', $firstError['detail']);
    }

    public function testItReturns422WhenUseCaseThrowsInvalidArgumentException(): void
    {
        $application = new HttpApplication(
            requestResolver: new SymfonyPackRequestResolver(
                serializer: SerializerFactory::create(),
                validator: ValidatorFactory::create(),
            ),
            findBoxSize: new class () extends FindBoxSize {
                public function __construct()
                {
                }

                public function execute(PackProductsCommand $command): \App\Application\DTO\PackingDecision
                {
                    throw new \InvalidArgumentException('synthetic validation error');
                }
            },
            serializer: SerializerFactory::create(),
            logger: new NullLogger(),
        );

        $response = $application->run(new Request(
            method: 'POST',
            uri: 'http://localhost/pack',
            headers: ['Content-Type' => 'application/json'],
            body: '{"products":[{"width":1,"height":1,"length":1,"weight":1}]}',
        ));
        $payload = $this->decodePayload($response);
        $firstError = $this->firstError($payload);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('VALIDATION_ERROR', $firstError['code']);
    }

    private function buildUseCase(): FindBoxSize
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

        $logger = new NullLogger();
        $packagingRepository = new InMemoryPackagingRepository(boxes: [
                new PackagingBox(id: 1, width: 3.0, height: 3.0, length: 3.0, maxWeight: 20.0),
            ]);
        $calculationRepository = new InMemoryPackingCalculationRepository();
        $calculateBoxSizeDecision = new CalculateBoxSizeDecisionMapper();
        $calculateBoxSize = new CalculateBoxSizeRunner(
            packingPolicyRegistry: $registry,
            logger: $logger,
        );
        $storePackingCalculation = new StorePackingCalculation(
            calculationRepository: $calculationRepository,
            logger: $logger,
        );

        return new FindBoxSize(
            refreshPolicy: new ManualResultsRequireRefreshPolicy(),
            packagingRepository: $packagingRepository,
            calculationRepository: $calculationRepository,
            commandMapper: new PackProductsCommandMapper(),
            requestHashBuilder: new \App\Application\Service\RequestHashBuilder(),
            calculateBoxSize: $calculateBoxSize,
            calculateBoxSizeDecision: $calculateBoxSizeDecision,
            storePackingCalculation: $storePackingCalculation,
            refreshPackingResult: new RefreshPackingResult(
                packagingRepository: $packagingRepository,
                calculateBoxSize: $calculateBoxSize,
                calculateBoxSizeDecision: $calculateBoxSizeDecision,
                storePackingCalculation: $storePackingCalculation,
                packingRefreshDifferenceSpecification: new PackingRefreshDifferenceSpecification(),
                logger: $logger,
            ),
            logger: $logger,
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
     * @return array{status: string, code: string, title: string, detail: string}
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
        $detail = $first['detail'] ?? null;

        self::assertIsString($status);
        self::assertIsString($code);
        self::assertIsString($title);
        self::assertIsString($detail);

        return [
            'status' => $status,
            'code' => $code,
            'title' => $title,
            'detail' => $detail,
        ];
    }
}
