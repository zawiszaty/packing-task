<?php

use App\Infrastructure\Persistence\Doctrine\EntityManagerFactory;
use Doctrine\ORM\Tools\Console\ConsoleRunner;

require __DIR__ . '/../vendor/autoload.php';

$entityManager = EntityManagerFactory::create();

return ConsoleRunner::createHelperSet($entityManager); // needed by vendor/bin/doctrine
