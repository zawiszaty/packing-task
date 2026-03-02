<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation;

use App\Infrastructure\Factory\SerializerFactory;
use App\Infrastructure\Factory\ValidatorFactory;
use App\Presentation\Http\Exception\RequestValidationException;
use App\Presentation\Http\SymfonyPackRequestResolver;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;

final class SymfonyPackRequestResolverTest extends TestCase
{
    private SymfonyPackRequestResolver $requestResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requestResolver = new SymfonyPackRequestResolver(
            serializer: SerializerFactory::create(),
            validator: ValidatorFactory::create(),
        );
    }

    public function testItResolvesValidPayload(): void
    {
        $request = new Request(
            method: 'POST',
            uri: 'http://localhost/pack',
            headers: ['Content-Type' => 'application/json'],
            body: '{"products":[{"width":2.0,"height":3.0,"length":4.0,"weight":1.5}]}',
        );

        $resolved = $this->requestResolver->resolve(request: $request);

        self::assertCount(1, $resolved->products);
        self::assertSame(2.0, $resolved->products[0]->width);
        self::assertSame(1.5, $resolved->products[0]->weight);
    }

    public function testItRejectsInvalidProductItemType(): void
    {
        $request = new Request(
            method: 'POST',
            uri: 'http://localhost/pack',
            headers: ['Content-Type' => 'application/json'],
            body: '{"products":[1]}',
        );

        try {
            $this->requestResolver->resolve(request: $request);
            self::fail('Expected validation exception to be thrown.');
        } catch (RequestValidationException $exception) {
            self::assertNotEmpty($exception->violations);
            self::assertSame('products[0]', $exception->violations[0]->field);
        }
    }

    public function testItAggregatesValidationErrors(): void
    {
        $request = new Request(
            method: 'POST',
            uri: 'http://localhost/pack',
            headers: ['Content-Type' => 'application/json'],
            body: '{"products":[{"width":0,"height":0,"length":1,"weight":-1}]}',
        );

        try {
            $this->requestResolver->resolve(request: $request);
            self::fail('Expected validation exception to be thrown.');
        } catch (RequestValidationException $exception) {
            self::assertCount(3, $exception->violations);
            self::assertSame('products[0].width', $exception->violations[0]->field);
            self::assertSame('products[0].height', $exception->violations[1]->field);
            self::assertSame('products[0].weight', $exception->violations[2]->field);
        }
    }

    public function testItRejectsMalformedJson(): void
    {
        $request = new Request(
            method: 'POST',
            uri: 'http://localhost/pack',
            headers: ['Content-Type' => 'application/json'],
            body: '{"products":',
        );

        try {
            $this->requestResolver->resolve(request: $request);
            self::fail('Expected validation exception to be thrown.');
        } catch (RequestValidationException $exception) {
            self::assertCount(1, $exception->violations);
            self::assertSame('body', $exception->violations[0]->field);
            self::assertSame('MALFORMED_JSON', $exception->violations[0]->code);
        }
    }

    public function testItRejectsMissingProductsField(): void
    {
        $request = new Request(
            method: 'POST',
            uri: 'http://localhost/pack',
            headers: ['Content-Type' => 'application/json'],
            body: '{}',
        );

        try {
            $this->requestResolver->resolve(request: $request);
            self::fail('Expected validation exception to be thrown.');
        } catch (RequestValidationException $exception) {
            self::assertNotEmpty($exception->violations);
            self::assertSame('products', $exception->violations[0]->field);
        }
    }

    public function testItRejectsProductWithMissingField(): void
    {
        $request = new Request(
            method: 'POST',
            uri: 'http://localhost/pack',
            headers: ['Content-Type' => 'application/json'],
            body: '{"products":[{"width":1.0,"height":1.0,"length":1.0}]}',
        );

        try {
            $this->requestResolver->resolve(request: $request);
            self::fail('Expected validation exception to be thrown.');
        } catch (RequestValidationException $exception) {
            self::assertNotEmpty($exception->violations);
            self::assertSame('products[0].weight', $exception->violations[0]->field);
        }
    }
}
