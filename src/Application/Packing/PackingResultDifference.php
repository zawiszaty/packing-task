<?php

declare(strict_types=1);

namespace App\Application\Packing;

enum PackingResultDifference: string
{
    case UNCHANGED = 'unchanged';
    case IMPROVED = 'improved';
    case REGRESSED = 'regressed';
    case CHANGED = 'changed';
}
