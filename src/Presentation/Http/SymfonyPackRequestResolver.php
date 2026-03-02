<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\DTO\PackProduct;
use App\Application\DTO\PackProductsCommand;
use App\Presentation\Http\DTO\Input\PackProductRequestDto;
use App\Presentation\Http\DTO\Input\PackRequestDto;
use App\Presentation\Http\DTO\Output\ValidationViolationDto;
use App\Presentation\Http\Exception\RequestValidationException;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SymfonyPackRequestResolver
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function resolve(RequestInterface $request): PackProductsCommand
    {
        try {
            $payload = $this->serializer->deserialize((string) $request->getBody(), PackRequestDto::class, 'json');
        } catch (ExceptionInterface) {
            throw new RequestValidationException([
                new ValidationViolationDto('body', 'Malformed JSON payload.', 'MALFORMED_JSON'),
            ]);
        }

        if (!$payload instanceof PackRequestDto) {
            throw new RequestValidationException([
                new ValidationViolationDto('body', 'Payload must be a JSON object.', 'INVALID_JSON_TYPE'),
            ]);
        }

        $violations = array_values(array_filter(
            $this->collectViolations($this->validator->validate($payload)),
            static fn (ValidationViolationDto $violation): bool => !str_starts_with($violation->field, 'products['),
        ));
        $products = [];

        foreach ($payload->products as $index => $item) {
            try {
                $productDto = $this->toProductDto($item);
            } catch (\InvalidArgumentException $exception) {
                $violations[] = new ValidationViolationDto(
                    field: sprintf('products[%d]', $index),
                    message: $exception->getMessage(),
                    code: 'INVALID_PRODUCT',
                );
                continue;
            }

            $productViolations = $this->collectViolations(
                $this->validator->validate($productDto),
                sprintf('products[%d].', $index),
            );

            if ($productViolations !== []) {
                array_push($violations, ...$productViolations);
                continue;
            }

            $products[] = new PackProduct(
                width: $productDto->width,
                height: $productDto->height,
                length: $productDto->length,
                weight: $productDto->weight,
            );
        }

        if ($violations !== []) {
            throw new RequestValidationException($violations);
        }

        return new PackProductsCommand($products);
    }

    private function toProductDto(mixed $item): PackProductRequestDto
    {
        if ($item instanceof PackProductRequestDto) {
            if (!$this->isProductFieldInitialized($item, 'width')
                && !$this->isProductFieldInitialized($item, 'height')
                && !$this->isProductFieldInitialized($item, 'length')
                && !$this->isProductFieldInitialized($item, 'weight')
            ) {
                throw new \InvalidArgumentException('Each product must be an object with width, height, length and weight.');
            }

            $dto = new PackProductRequestDto();
            $dto->width = $this->initializedFloatOrZero($item, 'width');
            $dto->height = $this->initializedFloatOrZero($item, 'height');
            $dto->length = $this->initializedFloatOrZero($item, 'length');
            $dto->weight = $this->initializedFloatOrZero($item, 'weight');

            return $dto;
        }

        if (is_array($item)) {
            $dto = new PackProductRequestDto();
            $dto->width = $this->toFloatOrZero($item['width'] ?? null);
            $dto->height = $this->toFloatOrZero($item['height'] ?? null);
            $dto->length = $this->toFloatOrZero($item['length'] ?? null);
            $dto->weight = $this->toFloatOrZero($item['weight'] ?? null);

            return $dto;
        }

        throw new \InvalidArgumentException('Each product must be an object with width, height, length and weight.');
    }

    /**
     * @return list<ValidationViolationDto>
     */
    private function collectViolations(ConstraintViolationListInterface $violations, string $prefix = ''): array
    {
        $items = [];
        foreach ($violations as $violation) {
            $propertyPath = $violation->getPropertyPath();
            $field = $propertyPath === '' ? rtrim($prefix, '.') : $prefix . $propertyPath;

            if ($field === '') {
                $field = 'body';
            }

            $items[] = new ValidationViolationDto(
                field: $field,
                message: (string) $violation->getMessage(),
                code: $violation->getCode() ?? 'VALIDATION_ERROR',
            );
        }

        return $items;
    }

    private function toFloatOrZero(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    private function initializedFloatOrZero(PackProductRequestDto $dto, string $property): float
    {
        if (!$this->isProductFieldInitialized($dto, $property)) {
            return 0.0;
        }

        /** @var mixed $value */
        $value = $dto->{$property};

        return $this->toFloatOrZero($value);
    }

    private function isProductFieldInitialized(PackProductRequestDto $dto, string $property): bool
    {
        $reflectionProperty = new \ReflectionProperty(PackProductRequestDto::class, $property);

        return $reflectionProperty->isInitialized($dto);
    }
}
