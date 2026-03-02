<?php

declare(strict_types=1);

namespace App\Domain\Policy\Refresh;

enum RefreshDecision: string
{
    case NO_REFRESH_NEEDED = 'no_refresh_needed';
    case REFRESH_REQUIRED = 'refresh_required';
}
