<?php

declare(strict_types=1);

namespace App\Presentation\Http\Exception;

use App\Presentation\Http\DTO\Output\ValidationViolationDto;
use InvalidArgumentException;

final class RequestValidationException extends InvalidArgumentException
{
    /**
     * @param list<ValidationViolationDto> $violations
     */
    public function __construct(
        public readonly array $violations,
        string $message = 'Request validation failed.',
    ) {
        parent::__construct($message);
    }
}
