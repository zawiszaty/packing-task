<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use App\Application\DTO\CalculationOutcome;
use App\Application\DTO\PackProduct;
use App\Application\DTO\PackProductsCommand;
use App\Application\Mapper\PackProductsCommandMapper;
use App\Application\Mapper\StoredCalculationPayloadMapper;
use App\Application\Service\RequestHashBuilder;
use App\Application\UseCase\CalculateBoxSize;
use App\Domain\Entity\PackagingBox;
use App\Domain\Entity\PackingCalculation;
use App\Domain\Policy\Packing\ProviderSelection;
use App\Domain\Policy\Refresh\ManualResultsRequireRefreshPolicy;
use App\Domain\Repository\PackagingRepository;
use App\Domain\Repository\PackingCalculationRepository;
use App\Domain\Service\SimpleSmallestBoxSelector;
use App\Infrastructure\CircuitBreaker\Simple\StaticCircuitBreaker;
use App\Infrastructure\Persistence\InMemory\InMemoryPackagingRepository;
use App\Infrastructure\Persistence\InMemory\InMemoryPackingCalculationRepository;
use App\Infrastructure\Policy\CircuitBreakerPackingPolicyRegistry;
use App\Infrastructure\Policy\ManualPackingPolicy;
use App\Infrastructure\Policy\ProviderPackingPolicy;
use App\Infrastructure\Provider\Stub\StubThreeDBinPackingClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tests\Support\Fake\Domain\Policy\ConfigurablePackingPolicy;
use Tests\Support\Fake\Domain\Policy\ConfigurablePackingPolicyRegistry;
use Tests\Support\Fake\Domain\Repository\ConfigurablePackagingRepository;
use Tests\Support\Fake\Infrastructure\Provider\ConfigurableThreeDBinPackingClient;
use Tests\Support\Fake\Infrastructure\Logger\InMemoryLogger;

final class CalculateBoxSizeTest extends TestCase
{
    private PackProductsCommandMapper $commandMapper;
    private StoredCalculationPayloadMapper $storedPayloadMapper;
    private RequestHashBuilder $requestHashBuilder;
    private PackProductsCommand $request;

    /** @var list<PackagingBox> */
    private array $boxes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->commandMapper = new PackProductsCommandMapper();
        $this->storedPayloadMapper = new StoredCalculationPayloadMapper();
        $this->requestHashBuilder = new RequestHashBuilder();
        $this->request = $this->request();
        $this->boxes = [
            new PackagingBox(1, 3.0, 3.0, 3.0, 20.0),
            new PackagingBox(2, 5.0, 5.0, 5.0, 20.0),
        ];
    }

    public function testItFallsBackToManualAndCachesResult(): void
    {
        $calculateBoxSize = $this->buildUseCase(
            providerAvailable: false,
            selectedBoxId: null,
        );

        $first = $calculateBoxSize->execute(command: $this->request);
        $second = $calculateBoxSize->execute(command: $this->request);

        self::assertSame(CalculationOutcome::BOX_RETURNED, $first->outcome);
        self::assertSame('manual', $first->source);
        self::assertNotNull($first->box);
        self::assertSame($first->requestHash, $second->requestHash);
        self::assertSame('manual', $second->source);
    }

    public function testItUsesProviderWhenAvailableAndProviderReturnsBox(): void
    {
        $calculateBoxSize = $this->buildUseCase(
            providerAvailable: true,
            selectedBoxId: 2,
        );

        $result = $calculateBoxSize->execute(command: $this->request);

        self::assertSame(CalculationOutcome::BOX_RETURNED, $result->outcome);
        self::assertSame('provider_3dbinpacking', $result->source);
        self::assertNotNull($result->box);
        self::assertSame(2, $result->box->id);
    }

    public function testItUsesFailoverPolicyFromRegistryWhenProviderThrows(): void
    {
        $circuitBreaker = new StaticCircuitBreaker(available: true);
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

        $calculateBoxSize = new CalculateBoxSize(
            packingPolicyRegistry: $registry,
            refreshPolicy: new ManualResultsRequireRefreshPolicy(),
            packagingRepository: new InMemoryPackagingRepository(boxes: [new PackagingBox(id: 1, width: 3.0, height: 3.0, length: 3.0, maxWeight: 20.0)]),
            calculationRepository: new InMemoryPackingCalculationRepository(),
            commandMapper: $this->commandMapper,
            storedPayloadMapper: $this->storedPayloadMapper,
            requestHashBuilder: $this->requestHashBuilder,
            logger: new NullLogger(),
        );

        $result = $calculateBoxSize->execute(command: $this->request);

        self::assertSame(CalculationOutcome::BOX_RETURNED, $result->outcome);
        self::assertSame('manual', $result->source);
        self::assertNotNull($result->box);
        self::assertSame(1, $result->box->id);
    }

    public function testItReturnsStoredCalculationWithoutRecomputingWhenNoRefreshNeeded(): void
    {
        $requestHash = $this->requestHashBuilder->fromProducts(products: $this->request->products);
        $stored = new PackingCalculation(
            id: 1,
            inputHash: $requestHash,
            normalizedRequest: '{"products":[]}',
            normalizedResult: '{"outcome":"BOX_RETURNED","reason":null,"message":null,"box":{"id":2,"width":5,"height":5,"length":5,"maxWeight":20}}',
            selectedBoxId: 2,
            providerSource: ProviderSelection::PROVIDER_3D_BIN_PACKING->value,
            createdAt: new \DateTimeImmutable(),
            refreshedAt: null,
        );

        $packagingRepository = new ConfigurablePackagingRepository(
            throwMessage: 'should not be called',
        );
        $calculationRepository = new InMemoryPackingCalculationRepository();
        $calculationRepository->save(calculation: $stored);
        $calculateBoxSize = $this->buildUseCase(
            providerAvailable: true,
            selectedBoxId: 1,
            packagingRepository: $packagingRepository,
            calculationRepository: $calculationRepository,
        );

        $result = $calculateBoxSize->execute(command: $this->request);

        self::assertSame(CalculationOutcome::BOX_RETURNED, $result->outcome);
        self::assertSame(2, $result->box?->id);
        self::assertSame(0, $packagingRepository->findAllCalls);
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
            createdAt: new \DateTimeImmutable(),
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

    public function testItReturnsModelErrorWhenStoredPayloadIsInvalid(): void
    {
        $requestHash = $this->requestHashBuilder->fromProducts(products: $this->request->products);
        $stored = new PackingCalculation(
            id: 1,
            inputHash: $requestHash,
            normalizedRequest: '{"products":[]}',
            normalizedResult: '{"outcome":"BROKEN"',
            selectedBoxId: null,
            providerSource: ProviderSelection::PROVIDER_3D_BIN_PACKING->value,
            createdAt: new \DateTimeImmutable(),
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

    public function testItReturnsModelErrorWhenStoredPayloadOutcomeIsUnknown(): void
    {
        $requestHash = $this->requestHashBuilder->fromProducts(products: $this->request->products);
        $stored = new PackingCalculation(
            id: 1,
            inputHash: $requestHash,
            normalizedRequest: '{"products":[]}',
            normalizedResult: '{"outcome":"UNKNOWN_OUTCOME","reason":null,"message":null,"box":null}',
            selectedBoxId: null,
            providerSource: ProviderSelection::PROVIDER_3D_BIN_PACKING->value,
            createdAt: new \DateTimeImmutable(),
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
        $calculateBoxSize = $this->buildUseCase(
            providerAvailable: false,
            selectedBoxId: null,
            packagingRepository: new InMemoryPackagingRepository(boxes: $tooSmallBoxes),
        );

        $result = $calculateBoxSize->execute(command: $this->request);

        self::assertSame(CalculationOutcome::NO_BOX_RETURNED, $result->outcome);
        self::assertNull($result->box);
        self::assertSame('NO_SINGLE_BOX_AVAILABLE', $result->reason);
        self::assertSame('Products cannot be packed into a single configured box.', $result->message);
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
            createdAt: new \DateTimeImmutable(),
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
            createdAt: new \DateTimeImmutable(),
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
        self::assertNull($records[0]['context']['previousSelectedBoxId']);
        self::assertSame(2, $records[0]['context']['refreshedSelectedBoxId']);
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
        $calculateBoxSize = new CalculateBoxSize(
            packingPolicyRegistry: $registry,
            refreshPolicy: new ManualResultsRequireRefreshPolicy(),
            packagingRepository: new InMemoryPackagingRepository(boxes: $this->boxes),
            calculationRepository: new InMemoryPackingCalculationRepository(),
            commandMapper: $this->commandMapper,
            storedPayloadMapper: $this->storedPayloadMapper,
            requestHashBuilder: $this->requestHashBuilder,
            logger: new NullLogger(),
        );

        $this->expectException(\RuntimeException::class);
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
        $calculateBoxSize = new CalculateBoxSize(
            packingPolicyRegistry: $registry,
            refreshPolicy: new ManualResultsRequireRefreshPolicy(),
            packagingRepository: new InMemoryPackagingRepository(boxes: $this->boxes),
            calculationRepository: new InMemoryPackingCalculationRepository(),
            commandMapper: $this->commandMapper,
            storedPayloadMapper: $this->storedPayloadMapper,
            requestHashBuilder: $this->requestHashBuilder,
            logger: new NullLogger(),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Packing policy failover loop detected.');

        $calculateBoxSize->execute(command: $this->request);
    }

    private function request(): PackProductsCommand
    {
        return new PackProductsCommand([
            new PackProduct(width: 2.0, height: 2.0, length: 2.0, weight: 1.0),
            new PackProduct(width: 1.0, height: 1.0, length: 1.0, weight: 1.0),
        ]);
    }

    private function buildUseCase(
        bool $providerAvailable,
        ?int $selectedBoxId,
        ?PackagingRepository $packagingRepository = null,
        ?PackingCalculationRepository $calculationRepository = null,
        ?LoggerInterface $logger = null,
    ): CalculateBoxSize {
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

        return new CalculateBoxSize(
            packingPolicyRegistry: $registry,
            refreshPolicy: new ManualResultsRequireRefreshPolicy(),
            packagingRepository: $packagingRepository ?? new InMemoryPackagingRepository(boxes: $this->boxes),
            calculationRepository: $calculationRepository ?? new InMemoryPackingCalculationRepository(),
            commandMapper: $this->commandMapper,
            storedPayloadMapper: $this->storedPayloadMapper,
            requestHashBuilder: $this->requestHashBuilder,
            logger: $logger ?? new NullLogger(),
        );
    }
}
