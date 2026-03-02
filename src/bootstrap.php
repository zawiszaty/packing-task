<?php

declare(strict_types=1);

use App\Infrastructure\Factory\HttpApplicationFactory;

require __DIR__ . '/../vendor/autoload.php';

return HttpApplicationFactory::create(dirname(__DIR__));
