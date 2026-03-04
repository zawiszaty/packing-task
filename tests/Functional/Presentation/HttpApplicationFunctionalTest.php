<?php

declare(strict_types=1);

namespace Tests\Functional\Presentation;

use App\Application\Mapper\PackProductsCommandMapper;
use App\Application\Service\Packing\CalculateBoxSize as CalculateBoxSizeRunner;
use App\Application\Service\Packing\CalculateBoxSizeDecisionMapper;
use App\Application\Service\Packing\PackingRefreshDifferenceSpecification;
use App\Application\Service\Packing\RefreshPackingResult;
use App\Application\Service\Packing\StorePackingCalculation;
use App\Application\Service\RequestHashBuilder;
use App\Application\UseCase\FindBoxSize;
use App\Domain\Policy\Refresh\ManualResultsRequireRefreshPolicy;
use App\Domain\Service\SimpleSmallestBoxSelector;
use App\Infrastructure\CircuitBreaker\Simple\StaticCircuitBreaker;
use App\Infrastructure\Factory\SerializerFactory;
use App\Infrastructure\Factory\ValidatorFactory;
use App\Infrastructure\Persistence\Doctrine\DoctrinePackagingRepository;
use App\Infrastructure\Persistence\Doctrine\DoctrinePackingCalculationRepository;
use App\Infrastructure\Persistence\Doctrine\Entity\Packaging;
use App\Infrastructure\Policy\CircuitBreakerPackingPolicyRegistry;
use App\Infrastructure\Policy\ManualPackingPolicy;
use App\Infrastructure\Policy\ProviderPackingPolicy;
use App\Infrastructure\Provider\Stub\StubThreeDBinPackingClient;
use App\Presentation\Http\HttpApplication;
use App\Presentation\Http\SymfonyPackRequestResolver;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Tests\Functional\Support\MySqlFunctionalTestCase;

final class HttpApplicationFunctionalTest extends MySqlFunctionalTestCase
{
    private HttpApplication $httpApplication;

    public function testItReturnsBoxAndPersistsCalculationInMySql(): void
    {
        $smallBox = new Packaging(2.0, 2.0, 2.0, 10.0);
        $largeBox = new Packaging(6.0, 6.0, 6.0, 20.0);
        $this->entityManager->persist($smallBox);
        $this->entityManager->persist($largeBox);
        $this->entityManager->flush();
        $smallBoxId = $smallBox->getId();
        self::assertNotNull($smallBoxId);

        $circuitBreaker = new StaticCircuitBreaker(available: false);
        $providerPolicy = new ProviderPackingPolicy(
            providerClient: new StubThreeDBinPackingClient(selectedBoxId: 2),
            circuitBreaker: $circuitBreaker,
        );
        $manualPolicy = new ManualPackingPolicy(selector: new SimpleSmallestBoxSelector());
        $registry = new CircuitBreakerPackingPolicyRegistry(
            circuitBreaker: $circuitBreaker,
            providerPolicy: $providerPolicy,
            manualPolicy: $manualPolicy,
        );

        $this->httpApplication = $this->createHttpApplication($registry);

        $response = $this->httpApplication->run(request: new Request(
            method: 'POST',
            uri: 'http://localhost/pack',
            headers: ['Content-Type' => 'application/json'],
            body: '{"products":[{"id":1,"width":1.0,"height":1.0,"length":1.0,"weight":1.0}]}',
        ));

        $payload = $this->decodePayload($response);
        $data = $this->requireArray($payload, 'data');
        $attributes = $this->requireArray($data, 'attributes');
        $box = $this->requireArray($attributes, 'box');
        $meta = $this->requireArray($payload, 'meta');
        $outcome = $attributes['outcome'] ?? null;
        $source = $meta['source'] ?? null;
        $boxId = $box['id'] ?? null;
        $requestHash = $meta['requestHash'] ?? null;
        self::assertIsString($outcome);
        self::assertIsString($source);
        self::assertIsInt($boxId);
        self::assertIsString($requestHash);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('BOX_RETURNED', $outcome);
        self::assertSame('manual', $source);
        self::assertSame($smallBoxId, $boxId);

        $saved = (new DoctrinePackingCalculationRepository($this->entityManager))
            ->findLatestByInputHash($requestHash);

        self::assertNotNull($saved);
        self::assertSame('manual', $saved->providerSource);
        self::assertSame($smallBoxId, $saved->selectedBoxId);
    }

    private function createHttpApplication(CircuitBreakerPackingPolicyRegistry $registry): HttpApplication
    {
        $logger = new NullLogger();
        $packagingRepository = new DoctrinePackagingRepository(entityManager: $this->entityManager);
        $calculationRepository = new DoctrinePackingCalculationRepository(entityManager: $this->entityManager);
        $calculateBoxSizeDecision = new CalculateBoxSizeDecisionMapper();
        $calculateBoxSize = new CalculateBoxSizeRunner(
            packingPolicyRegistry: $registry,
            logger: $logger,
        );
        $storePackingCalculation = new StorePackingCalculation(
            calculationRepository: $calculationRepository,
            logger: $logger,
        );

        return new HttpApplication(
            requestResolver: new SymfonyPackRequestResolver(
                serializer: SerializerFactory::create(),
                validator: ValidatorFactory::create(),
            ),
            findBoxSize: new FindBoxSize(
                refreshPolicy: new ManualResultsRequireRefreshPolicy(),
                packagingRepository: $packagingRepository,
                calculationRepository: $calculationRepository,
                commandMapper: new PackProductsCommandMapper(),
                requestHashBuilder: new RequestHashBuilder(),
                calculateBoxSize: $calculateBoxSize,
                calculateBoxSizeDecision: $calculateBoxSizeDecision,
                storePackingCalculation: $storePackingCalculation,
                refreshPackingResult: new RefreshPackingResult(
                    packagingRepository: $packagingRepository,
                    calculateBoxSize: $calculateBoxSize,
                    calculateBoxSizeDecisionMapper: $calculateBoxSizeDecision,
                    storePackingCalculation: $storePackingCalculation,
                    packingRefreshDifferenceSpecification: new PackingRefreshDifferenceSpecification(),
                    logger: $logger,
                ),
                logger: $logger,
            ),
            serializer: SerializerFactory::create(),
            logger: $logger,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function requireArray(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;
        self::assertIsArray($value);

        /** @var array<string, mixed> $value */
        return $value;
    }
}
