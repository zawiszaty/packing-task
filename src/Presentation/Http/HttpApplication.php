<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\DTO\PackingDecision;
use App\Application\UseCase\CalculateBoxSize;
use App\Presentation\Http\DTO\Output\ApiErrorDto;
use App\Presentation\Http\DTO\Output\ApiResponseDto;
use App\Presentation\Http\DTO\Output\PackBoxResponseDto;
use App\Presentation\Http\DTO\Output\PackDataDto;
use App\Presentation\Http\DTO\Output\PackingDecisionAttributesDto;
use App\Presentation\Http\DTO\Output\PackMetaResponseDto;
use App\Presentation\Http\DTO\Output\ValidationViolationDto;
use App\Presentation\Http\Exception\RequestValidationException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class HttpApplication
{
    public function __construct(
        private readonly SymfonyPackRequestResolver $requestResolver,
        private readonly CalculateBoxSize $calculateBoxSize,
        private readonly SerializerInterface $serializer,
    ) {
    }

    public function run(RequestInterface $request): ResponseInterface
    {
        try {
            $command = $this->requestResolver->resolve($request);
            $decision = $this->calculateBoxSize->execute($command);

            return $this->jsonResponse($this->toSuccessResponse($decision));
        } catch (RequestValidationException $exception) {
            return $this->jsonResponse(
                new ApiResponseDto(
                    errors: array_map(
                        fn (ValidationViolationDto $violation): ApiErrorDto => new ApiErrorDto(
                            status: '422',
                            code: $violation->code,
                            title: 'Invalid request body',
                            detail: $violation->message,
                        ),
                        $exception->violations,
                    ),
                ),
                422,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse(
                new ApiResponseDto(
                    errors: [
                        new ApiErrorDto(
                            status: '422',
                            code: 'VALIDATION_ERROR',
                            title: 'Invalid request body',
                            detail: $exception->getMessage(),
                        ),
                    ],
                ),
                422,
            );
        } catch (\Throwable $exception) {
            return $this->jsonResponse(
                new ApiResponseDto(
                    errors: [
                        new ApiErrorDto(
                            status: '500',
                            code: 'INTERNAL_ERROR',
                            title: 'Internal server error',
                            detail: $exception->getMessage(),
                        ),
                    ],
                ),
                500,
            );
        }
    }

    private function jsonResponse(ApiResponseDto $responseDto, int $statusCode = 200): ResponseInterface
    {
        return new Response(
            $statusCode,
            ['Content-Type' => 'application/json'],
            $this->serializer->serialize($responseDto, 'json'),
        );
    }

    private function toSuccessResponse(PackingDecision $decision): ApiResponseDto
    {
        return new ApiResponseDto(
            data: new PackDataDto(
                type: 'packing-decision',
                id: $decision->requestHash,
                attributes: new PackingDecisionAttributesDto(
                    outcome: $decision->outcome->value,
                    reason: $decision->reason,
                    message: $decision->message,
                    box: $decision->box === null ? null : new PackBoxResponseDto(
                        id: $decision->box->id,
                        width: $decision->box->width,
                        height: $decision->box->height,
                        length: $decision->box->length,
                        maxWeight: $decision->box->maxWeight,
                    ),
                ),
            ),
            meta: new PackMetaResponseDto(
                source: $decision->source,
                requestHash: $decision->requestHash,
            ),
        );
    }
}
