<?php

declare(strict_types=1);

namespace App\Domain\Policy\Packing;

enum ProviderSelection: string
{
    case PROVIDER_3D_BIN_PACKING = 'provider_3dbinpacking';
    case MANUAL = 'manual';
}
