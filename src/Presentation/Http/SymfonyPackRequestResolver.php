<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\DTO\PackProduct;
use App\Application\DTO\PackProductsCommand;
use App\Presentation\Http\DTO\Input\PackProductRequestDto;
use App\Presentation\Http\DTO\Input\PackRequestDto;
use App\Presentation\Http\DTO\Output\ValidationViolationDto;
use App\Presentation\Http\Exception\RequestValidationException;
use LogicException;
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

        $violations = $this->collectViolations($this->validator->validate($payload));

        if ($violations !== []) {
            throw new RequestValidationException($violations);
        }

        $products = [];
        foreach ($payload->products as $item) {
            if (!$item instanceof PackProductRequestDto || $item->width === null || $item->height === null || $item->length === null || $item->weight === null) {
                throw new LogicException('Validated product payload is unexpectedly missing required fields.');
            }

            $products[] = new PackProduct(
                width: $item->width,
                height: $item->height,
                length: $item->length,
                weight: $item->weight,
            );
        }

        return new PackProductsCommand($products);
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

}
