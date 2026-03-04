<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use App\Application\DTO\CalculationOutcome;
use App\Application\DTO\PackProduct;
use App\Application\DTO\PackProductsCommand;
use App\Application\Mapper\PackProductsCommandMapper;
use App\Application\Service\Packing\CalculateBoxSize as CalculateBoxSizeRunner;
use App\Application\Service\Packing\CalculateBoxSizeDecisionMapper;
use App\Application\Service\Packing\PackingRefreshDifferenceSpecification;
use App\Application\Service\Packing\RefreshPackingResult;
use App\Application\Service\Packing\StorePackingCalculation;
use App\Application\Service\RequestHashBuilder;
use App\Application\UseCase\FindBoxSize;
use App\Domain\Entity\PackagingBox;
use App\Domain\Entity\PackingCalculation;
use App\Domain\Policy\Packing\PackingPolicyRegistry;
use App\Domain\Policy\Packing\ProviderSelection;
use App\Domain\Policy\Refresh\ManualResultsRequireRefreshPolicy;
use App\Domain\Repository\PackagingRepository;
use App\Domain\Repository\PackingCalculationRepository;
use App\Domain\Service\SimpleSmallestBoxSelector;
use App\Infrastructure\CircuitBreaker\Simple\StaticCircuitBreaker;
use App\Infrastructure\Policy\CircuitBreakerPackingPolicyRegistry;
use App\Infrastructure\Policy\ManualPackingPolicy;
use App\Infrastructure\Policy\ProviderPackingPolicy;
use App\Infrastructure\Provider\Stub\StubThreeDBinPackingClient;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Tests\Support\Fake\Domain\Policy\ConfigurablePackingPolicy;
use Tests\Support\Fake\Domain\Policy\ConfigurablePackingPolicyRegistry;
use Tests\Support\Fake\Infrastructure\Logger\InMemoryLogger;
use Tests\Support\Fake\Infrastructure\Persistence\InMemoryPackagingRepository;
use Tests\Support\Fake\Infrastructure\Persistence\InMemoryPackingCalculationRepository;
use Tests\Support\Fake\Infrastructure\Provider\ConfigurableThreeDBinPackingClient;

final class FindBoxSizeTest extends TestCase
{
    private PackProductsCommandMapper $commandMapper;
    private RequestHashBuilder $requestHashBuilder;
    private PackProductsCommand $request;

    /** @var list<PackagingBox> */
    private array $boxes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->commandMapper = new PackProductsCommandMapper();
        $this->requestHashBuilder = new RequestHashBuilder();
        $this->request = $this->request();
        $this->boxes = [
            new PackagingBox(1, 3.0, 3.0, 3.0, 20.0),
            new PackagingBox(2, 5.0, 5.0, 5.0, 20.0),
        ];
    }

    public function testItFallsBackToManualAndCachesResult(): void
    {
        $logger = new InMemoryLogger();
        $calculateBoxSize = $this->buildUseCase(
            providerAvailable: false,
            selectedBoxId: null,
            logger: $logger,
        );

        $first = $calculateBoxSize->execute(command: $this->request);
        $second = $calculateBoxSize->execute(command: $this->request);

        self::assertSame(CalculationOutcome::BOX_RETURNED, $first->outcome);
        self::assertSame('manual', $first->source);
        self::assertNotNull($first->box);
        self::assertSame($first->requestHash, $second->requestHash);
        self::assertSame('manual', $second->source);

        $records = $logger->recordsBy(level: 'info', message: 'packing.box_returned');
        self::assertCount(1, $records);
        self::assertSame($first->requestHash, $records[0]['context']['requestHash']);
        self::assertSame('manual', $records[0]['context']['source']);
        self::assertSame(1, $records[0]['context']['boxId']);
    }

    public function testItUsesProviderWhenAvailableAndProviderReturnsBox(): void
    {
        $logger = new InMemoryLogger();
        $calculateBoxSize = $this->buildUseCase(
            providerAvailable: true,
            selectedBoxId: 2,
            logger: $logger,
        );

        $result = $calculateBoxSize->execute(command: $this->request);

        self::assertSame(CalculationOutcome::BOX_RETURNED, $result->outcome);
        self::assertSame('provider_3dbinpacking', $result->source);
        self::assertNotNull($result->box);
        self::assertSame(2, $result->box->id);

        $records = $logger->recordsBy(level: 'info', message: 'packing.box_returned');
        self::assertCount(1, $records);
        self::assertSame($result->requestHash, $records[0]['context']['requestHash']);
        self::assertSame('provider_3dbinpacking', $records[0]['context']['source']);
        self::assertSame(2, $records[0]['context']['boxId']);
    }

    public function testItUsesFailoverPolicyFromRegistryWhenProviderThrows(): void
    {
        $circuitBreaker = new StaticCircuitBreaker(available: true);
        $logger = new InMemoryLogger();
        $providerPolicy = new ProviderPackingPolicy(
            providerClient: new ConfigurableThreeDBinPackingClient(throwMessage: 'provider is down'),
            circuitBreaker: $circuitBreaker,
        );
        $manualPolicy = new ManualPackingPolicy(selector: new SimpleSmallestBoxSelector());
        $registry = new CircuitBreakerPackingPolicyRegistry(
            circuitBreaker: $circuitBreaker,
            providerPolicy: $providerPolicy,
            manualPolicy: $manualPolicy,
        );

        $calculateBoxSize = $this->createFindBoxSize(
            packingPolicyRegistry: $registry,
            packagingRepository: new InMemoryPackagingRepository(boxes: [new PackagingBox(id: 1, width: 3.0, height: 3.0, length: 3.0, maxWeight: 20.0)]),
            calculationRepository: new InMemoryPackingCalculationRepository(),
            logger: $logger,
        );

        $result = $calculateBoxSize->execute(command: $this->request);

        self::assertSame(CalculationOutcome::BOX_RETURNED, $result->outcome);
        self::assertSame('manual', $result->source);
        self::assertNotNull($result->box);
        self::assertSame(1, $result->box->id);

        $records = $logger->recordsBy(level: 'error', message: 'packing.policy_failed_using_failover');
        self::assertCount(1, $records);
        self::assertSame($result->requestHash, $records[0]['context']['requestHash']);
        self::assertSame('provider_3dbinpacking', $records[0]['context']['policy']);
        self::assertSame('manual', $records[0]['context']['failoverPolicy']);
    }

    public function testItReturnsStoredCalculationBySelectedBoxId(): void
    {
        $requestHash = $this->requestHashBuilder->fromProducts(products: $this->request->products);
        $stored = new PackingCalculation(
            id: 1,
            inputHash: $requestHash,
            normalizedRequest: '{"products":[]}',
            normalizedResult: '{"outcome":"BOX_RETURNED","reason":null,"message":null,"box":{"id":2,"width":5,"height":5,"length":5,"maxWeight":20}}',
            selectedBoxId: 2,
            providerSource: ProviderSelection::PROVIDER_3D_BIN_PACKING->value,
            createdAt: new DateTimeImmutable(),
            refreshedAt: null,
        );

        $calculationRepository = new InMemoryPackingCalculationRepository();
        $calculationRepository->save(calculation: $stored);
        $calculateBoxSize = $this->buildUseCase(
            providerAvailable: true,
            selectedBoxId: 1,
            calculationRepository: $calculationRepository,
        );

        $result = $calculateBoxSize->execute(command: $this->request);

        self::assertSame(CalculationOutcome::BOX_RETURNED, $result->outcome);
        self::assertSame(2, $result->box?->id);
    }

    public function testItRefreshesManualStoredCalculationInBackgroundAndServesStoredValue(): void
    {
        $requestHash = $this->requestHashBuilder->fromProducts(products: $this->request->products);
        $stored = new PackingCalculation(
            id: 1,
            inputHash: $requestHash,
            normalizedRequest: '{"products":[]}',
            normalizedResult: '{"outcome":"BOX_RETURNED","reason":null,"message":null,"box":{"id":1,"width":3,"height":3,"length":3,"maxWeight":20}}',
            selectedBoxId: 1,
            providerSource: ProviderSelection::MANUAL->value,
            createdAt: new DateTimeImmutable(),
            refreshedAt: null,
        );
        $calculationRepository = new InMemoryPackingCalculationRepository();
        $calculationRepository->save(calculation: $stored);
        $calculateBoxSize = $this->buildUseCase(
            providerAvailable: true,
            selectedBoxId: 2,
            calculationRepository: $calculationRepository,
        );

        $first = $calculateBoxSize->execute(command: $this->request);
        $second = $calculateBoxSize->execute(command: $this->request);

        self::assertSame('manual', $first->source);
        self::assertSame(1, $first->box?->id);
        self::assertSame('provider_3dbinpacking', $second->source);
        self::assertSame(2, $second->box?->id);
    }

    public function testItReturnsNoBoxReturnedWhenStoredCalculationHasNoSelectedBoxId(): void
    {
        $requestHash = $this->requestHashBuilder->fromProducts(products: $this->request->products);
        $stored = new PackingCalculation(
            id: 1,
            inputHash: $requestHash,
            normalizedRequest: '{"products":[]}',
            normalizedResult: '{"outcome":"BROKEN"',
            selectedBoxId: null,
            providerSource: ProviderSelection::PROVIDER_3D_BIN_PACKING->value,
            createdAt: new DateTimeImmutable(),
            refreshedAt: null,
        );
        $calculationRepository = new InMemoryPackingCalculationRepository();
        $calculationRepository->save(calculation: $stored);
        $calculateBoxSize = $this->buildUseCase(
            providerAvailable: true,
            selectedBoxId: 2,
            calculationRepository: $calculationRepository,
        );

        $result = $calculateBoxSize->execute(command: $this->request);

        self::assertSame(CalculationOutcome::NO_BOX_RETURNED, $result->outcome);
        self::assertSame('NO_SINGLE_BOX_AVAILABLE', $result->reason);
        self::assertSame('Products cannot be packed into a single configured box.', $result->message);
    }

    public function testItReturnsModelErrorWhenStoredSelectedBoxIdDoesNotExistInConfiguredBoxes(): void
    {
        $requestHash = $this->requestHashBuilder->fromProducts(products: $this->request->products);
        $stored = new PackingCalculation(
            id: 1,
            inputHash: $requestHash,
            normalizedRequest: '{"products":[]}',
            normalizedResult: '{"outcome":"UNKNOWN_OUTCOME","reason":null,"message":null,"box":null}',
            selectedBoxId: 999,
            providerSource: ProviderSelection::PROVIDER_3D_BIN_PACKING->value,
            createdAt: new DateTimeImmutable(),
            refreshedAt: null,
        );
        $calculationRepository = new InMemoryPackingCalculationRepository();
        $calculationRepository->save(calculation: $stored);
        $calculateBoxSize = $this->buildUseCase(
            providerAvailable: true,
            selectedBoxId: 2,
            calculationRepository: $calculationRepository,
        );

        $result = $calculateBoxSize->execute(command: $this->request);

        self::assertSame(CalculationOutcome::NO_BOX_RETURNED, $result->outcome);
        self::assertSame('MODEL_ERROR', $result->reason);
        self::assertSame('Cached result payload is invalid.', $result->message);
    }

    public function testItReturnsNoBoxReturnedWhenNoPolicyCanPack(): void
    {
        $tooSmallBoxes = [
            new PackagingBox(1, 1.0, 1.0, 1.0, 1.0),
            new PackagingBox(2, 1.5, 1.5, 1.5, 1.0),
        ];
        $logger = new InMemoryLogger();
        $calculateBoxSize = $this->buildUseCase(
            providerAvailable: false,
            selectedBoxId: null,
            packagingRepository: new InMemoryPackagingRepository(boxes: $tooSmallBoxes),
            logger: $logger,
        );

        $result = $calculateBoxSize->execute(command: $this->request);

        self::assertSame(CalculationOutcome::NO_BOX_RETURNED, $result->outcome);
        self::assertNull($result->box);
        self::assertSame('NO_SINGLE_BOX_AVAILABLE', $result->reason);
        self::assertSame('Products cannot be packed into a single configured box.', $result->message);

        $records = $logger->recordsBy(level: 'info', message: 'packing.no_box_returned');
        self::assertCount(1, $records);
        self::assertSame($result->requestHash, $records[0]['context']['requestHash']);
        self::assertSame('manual', $records[0]['context']['source']);
    }

    public function testItRefreshesManualStoredCalculationToNoBoxWhenProviderCannotPack(): void
    {
        $logger = new InMemoryLogger();
        $requestHash = $this->requestHashBuilder->fromProducts(products: $this->request->products);
        $stored = new PackingCalculation(
            id: 1,
            inputHash: $requestHash,
            normalizedRequest: '{"products":[]}',
            normalizedResult: '{"outcome":"BOX_RETURNED","reason":null,"message":null,"box":{"id":1,"width":3,"height":3,"length":3,"maxWeight":20}}',
            selectedBoxId: 1,
            providerSource: ProviderSelection::MANUAL->value,
            createdAt: new DateTimeImmutable(),
            refreshedAt: null,
        );
        $calculationRepository = new InMemoryPackingCalculationRepository();
        $calculationRepository->save(calculation: $stored);
        $calculateBoxSize = $this->buildUseCase(
            providerAvailable: true,
            selectedBoxId: null,
            calculationRepository: $calculationRepository,
            logger: $logger,
        );

        $first = $calculateBoxSize->execute(command: $this->request);
        $second = $calculateBoxSize->execute(command: $this->request);

        self::assertSame(CalculationOutcome::BOX_RETURNED, $first->outcome);
        self::assertSame('manual', $first->source);
        self::assertSame(1, $first->box?->id);

        self::assertSame(CalculationOutcome::NO_BOX_RETURNED, $second->outcome);
        self::assertSame('provider_3dbinpacking', $second->source);
        self::assertNull($second->box);
        self::assertSame('NO_SINGLE_BOX_AVAILABLE', $second->reason);

        $records = $logger->recordsBy(level: 'error', message: 'packing.refresh_result_changed');
        self::assertCount(1, $records);
        self::assertSame($requestHash, $records[0]['context']['requestHash']);
        self::assertSame('manual', $records[0]['context']['previousSource']);
        self::assertSame('provider_3dbinpacking', $records[0]['context']['refreshedSource']);
        self::assertSame('regressed', $records[0]['context']['difference']);
        self::assertSame(1, $records[0]['context']['previousSelectedBoxId']);
        self::assertNull($records[0]['context']['refreshedSelectedBoxId']);
    }

    public function testItLogsInfoWhenRefreshChangesManualNoBoxToProviderBox(): void
    {
        $logger = new InMemoryLogger();
        $requestHash = $this->requestHashBuilder->fromProducts(products: $this->request->products);
        $stored = new PackingCalculation(
            id: 1,
            inputHash: $requestHash,
            normalizedRequest: '{"products":[]}',
            normalizedResult: '{"outcome":"NO_BOX_RETURNED","reason":"NO_SINGLE_BOX_AVAILABLE","message":"Products cannot be packed into a single configured box.","box":null}',
            selectedBoxId: null,
            providerSource: ProviderSelection::MANUAL->value,
            createdAt: new DateTimeImmutable(),
            refreshedAt: null,
        );
        $calculationRepository = new InMemoryPackingCalculationRepository();
        $calculationRepository->save(calculation: $stored);
        $calculateBoxSize = $this->buildUseCase(
            providerAvailable: true,
            selectedBoxId: 2,
            calculationRepository: $calculationRepository,
            logger: $logger,
        );

        $calculateBoxSize->execute(command: $this->request);

        $records = $logger->recordsBy(level: 'info', message: 'packing.refresh_result_changed');
        self::assertCount(1, $records);
        self::assertSame($requestHash, $records[0]['context']['requestHash']);
        self::assertSame('manual', $records[0]['context']['previousSource']);
        self::assertSame('provider_3dbinpacking', $records[0]['context']['refreshedSource']);
        self::assertSame('improved', $records[0]['context']['difference']);
        self::assertNull($records[0]['context']['previousSelectedBoxId']);
        self::assertSame(2, $records[0]['context']['refreshedSelectedBoxId']);
    }

    public function testItStoresFreshCalculationWithExpectedShape(): void
    {
        $calculationRepository = new InMemoryPackingCalculationRepository();
        $calculateBoxSize = $this->buildUseCase(
            providerAvailable: true,
            selectedBoxId: 2,
            calculationRepository: $calculationRepository,
        );

        $result = $calculateBoxSize->execute(command: $this->request);
        $stored = $calculationRepository->findLatestByInputHash($result->requestHash);

        self::assertNotNull($stored);
        self::assertSame(0, $stored->id);
        self::assertNull($stored->refreshedAt);

        $normalizedRequest = json_decode($stored->normalizedRequest, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($normalizedRequest);
        self::assertEquals([
            ['width' => 2.0, 'height' => 2.0, 'length' => 2.0, 'weight' => 1.0],
            ['width' => 1.0, 'height' => 1.0, 'length' => 1.0, 'weight' => 1.0],
        ], $normalizedRequest);

        $normalizedResult = json_decode($stored->normalizedResult, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($normalizedResult);
        self::assertSame('BOX_RETURNED', $normalizedResult['outcome'] ?? null);
        self::assertNull($normalizedResult['reason'] ?? null);
        self::assertNull($normalizedResult['message'] ?? null);
        $box = $normalizedResult['box'] ?? null;
        self::assertIsArray($box);
        self::assertSame(2, $box['id'] ?? null);
    }

    public function testItStoresRefreshedCalculationWithRefreshTimestamp(): void
    {
        $requestHash = $this->requestHashBuilder->fromProducts(products: $this->request->products);
        $calculationRepository = new InMemoryPackingCalculationRepository();
        $calculationRepository->save(new PackingCalculation(
            id: 1,
            inputHash: $requestHash,
            normalizedRequest: '{"products":[]}',
            normalizedResult: '{"outcome":"BOX_RETURNED","reason":null,"message":null,"box":{"id":1,"width":3,"height":3,"length":3,"maxWeight":20}}',
            selectedBoxId: 1,
            providerSource: ProviderSelection::MANUAL->value,
            createdAt: new DateTimeImmutable(),
            refreshedAt: null,
        ));
        $calculateBoxSize = $this->buildUseCase(
            providerAvailable: true,
            selectedBoxId: 2,
            calculationRepository: $calculationRepository,
        );

        $calculateBoxSize->execute(command: $this->request);
        $stored = $calculationRepository->findLatestByInputHash($requestHash);

        self::assertNotNull($stored);
        self::assertSame('provider_3dbinpacking', $stored->providerSource);
        self::assertSame(2, $stored->selectedBoxId);
        self::assertNotNull($stored->refreshedAt);

        $normalizedResult = json_decode($stored->normalizedResult, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($normalizedResult);
        self::assertSame('BOX_RETURNED', $normalizedResult['outcome'] ?? null);
        $box = $normalizedResult['box'] ?? null;
        self::assertIsArray($box);
        self::assertSame(2, $box['id'] ?? null);
    }

    public function testItRethrowsWhenPolicyFailsAndFailoverPolicyIsMissing(): void
    {
        $registry = new CircuitBreakerPackingPolicyRegistry(
            circuitBreaker: new StaticCircuitBreaker(available: true),
            providerPolicy: new ConfigurablePackingPolicy(
                throwMessage: 'provider blew up',
                failoverSource: 'non_existing_policy_source',
            ),
            manualPolicy: new ManualPackingPolicy(selector: new SimpleSmallestBoxSelector()),
        );
        $calculateBoxSize = $this->createFindBoxSize(
            packingPolicyRegistry: $registry,
            packagingRepository: new InMemoryPackagingRepository(boxes: $this->boxes),
            calculationRepository: new InMemoryPackingCalculationRepository(),
            logger: new NullLogger(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('provider blew up');

        $calculateBoxSize->execute(command: $this->request);
    }

    public function testItThrowsWhenFailoverPoliciesCreateLoop(): void
    {
        $providerPolicy = new ConfigurablePackingPolicy(
            throwMessage: 'provider blew up',
            source: 'provider',
            failoverSource: 'manual',
        );
        $manualPolicy = new ConfigurablePackingPolicy(
            throwMessage: 'manual blew up',
            source: 'manual',
            failoverSource: 'provider',
        );
        $registry = new ConfigurablePackingPolicyRegistry(
            resolvedSource: 'provider',
            policiesBySource: [
                'provider' => $providerPolicy,
                'manual' => $manualPolicy,
            ],
        );
        $calculateBoxSize = $this->createFindBoxSize(
            packingPolicyRegistry: $registry,
            packagingRepository: new InMemoryPackagingRepository(boxes: $this->boxes),
            calculationRepository: new InMemoryPackingCalculationRepository(),
            logger: new NullLogger(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Packing policy failover loop detected.');

        $calculateBoxSize->execute(command: $this->request);
    }

    public function testItReturnsImmediatelyAfterFirstSuccessfulPackCall(): void
    {
        $policy = new class () implements \App\Domain\Policy\Packing\PackingPolicy {
            public int $packCalls = 0;

            public function pack(\App\Domain\ValueObject\PackingRequest $request, array $boxes): ?PackagingBox
            {
                ++$this->packCalls;
                if ($this->packCalls >= 2) {
                    throw new RuntimeException('Second call should never happen.');
                }

                return $boxes[0] ?? null;
            }

            public function source(): string
            {
                return 'provider';
            }

            public function failoverPolicySource(): string
            {
                return 'provider';
            }
        };
        $registry = new ConfigurablePackingPolicyRegistry(
            resolvedSource: 'provider',
            policiesBySource: [
                'provider' => $policy,
            ],
        );
        $calculateBoxSize = $this->createFindBoxSize(
            packingPolicyRegistry: $registry,
            packagingRepository: new InMemoryPackagingRepository(boxes: $this->boxes),
            calculationRepository: new InMemoryPackingCalculationRepository(),
            logger: new NullLogger(),
        );

        $result = $calculateBoxSize->execute(command: $this->request);

        self::assertSame(CalculationOutcome::BOX_RETURNED, $result->outcome);
        self::assertSame(1, $policy->packCalls);
    }

    public function testItDetectsFailoverLoopBeforePoliciesSwitchToSelfFailover(): void
    {
        $providerPolicy = new class () implements \App\Domain\Policy\Packing\PackingPolicy {
            public int $packCalls = 0;

            public function pack(\App\Domain\ValueObject\PackingRequest $request, array $boxes): ?PackagingBox
            {
                ++$this->packCalls;
                throw new RuntimeException('provider blew up');
            }

            public function source(): string
            {
                return 'provider';
            }

            public function failoverPolicySource(): string
            {
                // Mutated loop-detection should not spin forever. After 2 calls we force self-failover to break.
                return $this->packCalls >= 2 ? 'provider' : 'manual';
            }
        };
        $manualPolicy = new class () implements \App\Domain\Policy\Packing\PackingPolicy {
            public int $packCalls = 0;

            public function pack(\App\Domain\ValueObject\PackingRequest $request, array $boxes): ?PackagingBox
            {
                ++$this->packCalls;
                throw new RuntimeException('manual blew up');
            }

            public function source(): string
            {
                return 'manual';
            }

            public function failoverPolicySource(): string
            {
                return 'provider';
            }
        };
        $registry = new ConfigurablePackingPolicyRegistry(
            resolvedSource: 'provider',
            policiesBySource: [
                'provider' => $providerPolicy,
                'manual' => $manualPolicy,
            ],
        );
        $calculateBoxSize = $this->createFindBoxSize(
            packingPolicyRegistry: $registry,
            packagingRepository: new InMemoryPackagingRepository(boxes: $this->boxes),
            calculationRepository: new InMemoryPackingCalculationRepository(),
            logger: new NullLogger(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Packing policy failover loop detected.');

        $calculateBoxSize->execute(command: $this->request);
    }

    private function request(): PackProductsCommand
    {
        return new PackProductsCommand([
            new PackProduct(width: 2.0, height: 2.0, length: 2.0, weight: 1.0, id: 1),
            new PackProduct(width: 1.0, height: 1.0, length: 1.0, weight: 1.0, id: 2),
        ]);
    }

    private function buildUseCase(
        bool $providerAvailable,
        ?int $selectedBoxId,
        ?PackagingRepository $packagingRepository = null,
        ?PackingCalculationRepository $calculationRepository = null,
        ?LoggerInterface $logger = null,
    ): FindBoxSize {
        $circuitBreaker = new StaticCircuitBreaker(available: $providerAvailable);
        $providerPolicy = new ProviderPackingPolicy(
            providerClient: new StubThreeDBinPackingClient(selectedBoxId: $selectedBoxId),
            circuitBreaker: $circuitBreaker,
        );
        $manualPolicy = new ManualPackingPolicy(selector: new SimpleSmallestBoxSelector());
        $registry = new CircuitBreakerPackingPolicyRegistry(
            circuitBreaker: $circuitBreaker,
            providerPolicy: $providerPolicy,
            manualPolicy: $manualPolicy,
        );

        return $this->createFindBoxSize(
            packingPolicyRegistry: $registry,
            packagingRepository: $packagingRepository ?? new InMemoryPackagingRepository(boxes: $this->boxes),
            calculationRepository: $calculationRepository ?? new InMemoryPackingCalculationRepository(),
            logger: $logger ?? new NullLogger(),
        );
    }

    private function createFindBoxSize(
        PackingPolicyRegistry $packingPolicyRegistry,
        PackagingRepository $packagingRepository,
        PackingCalculationRepository $calculationRepository,
        LoggerInterface $logger,
    ): FindBoxSize {
        $calculateBoxSizeDecision = new CalculateBoxSizeDecisionMapper();
        $calculateBoxSize = new CalculateBoxSizeRunner(
            packingPolicyRegistry: $packingPolicyRegistry,
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
            commandMapper: $this->commandMapper,
            requestHashBuilder: $this->requestHashBuilder,
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
        );
    }
}
