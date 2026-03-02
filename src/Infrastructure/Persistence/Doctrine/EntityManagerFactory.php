<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;

final class EntityManagerFactory
{
    public static function create(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/../../../'], true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy());

        return new EntityManager(DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => 'shipmonk-packing-mysql',
            'port' => 3306,
            'user' => 'root',
            'password' => 'secret',
            'dbname' => 'packing',
        ]), $config);
    }
}
