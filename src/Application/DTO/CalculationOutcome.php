<?php

declare(strict_types=1);

namespace App\Application\DTO;

enum CalculationOutcome: string
{
    case BOX_RETURNED = 'BOX_RETURNED';
    case NO_BOX_RETURNED = 'NO_BOX_RETURNED';
    case REQUEST_REJECTED = 'REQUEST_REJECTED';
}
