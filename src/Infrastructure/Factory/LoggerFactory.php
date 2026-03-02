<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class LoggerFactory
{
    public static function create(): LoggerInterface
    {
        $handler = new StreamHandler('php://stderr', Level::Info);
        $handler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, appendNewline: true));

        $logger = new Logger('packing-app');
        $logger->pushHandler($handler);

        return $logger;
    }
}
