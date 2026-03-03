<?php

declare(strict_types=1);

namespace App\Application\Service\Packing;

enum PackingResultDifference: string
{
    case UNCHANGED = 'unchanged';
    case IMPROVED = 'improved';
    case REGRESSED = 'regressed';
    case CHANGED = 'changed';
}
