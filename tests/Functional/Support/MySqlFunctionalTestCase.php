<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use App\Infrastructure\Persistence\Doctrine\Entity\Packaging;
use App\Infrastructure\Persistence\Doctrine\Entity\PackingCalculation;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

abstract class MySqlFunctionalTestCase extends TestCase
{
    private static bool $databaseReady = false;
    private static bool $schemaReady = false;

    protected EntityManager $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$databaseReady) {
            $this->ensureTestDatabaseExists();
            self::$databaseReady = true;
        }

        $this->entityManager = $this->createEntityManager();

        if (!self::$schemaReady) {
            $this->resetSchema($this->entityManager);
            self::$schemaReady = true;
        }

        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        $this->entityManager->clear();
        $connection->close();

        parent::tearDown();
    }

    private function ensureTestDatabaseExists(): void
    {
        $connection = $this->createAdminConnection();
        $connection->executeStatement('CREATE DATABASE IF NOT EXISTS packing_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $connection->close();
    }

    private function createAdminConnection(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => 'shipmonk-packing-mysql',
            'user' => 'root',
            'password' => 'secret',
        ]);
    }

    private function createEntityManager(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/../../../src'], true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy());

        return new EntityManager(DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => 'shipmonk-packing-mysql',
            'user' => 'root',
            'password' => 'secret',
            'dbname' => 'packing_test',
        ]), $config);
    }

    private function resetSchema(EntityManager $entityManager): void
    {
        $classes = [
            $entityManager->getClassMetadata(Packaging::class),
            $entityManager->getClassMetadata(PackingCalculation::class),
        ];

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);
    }
}
