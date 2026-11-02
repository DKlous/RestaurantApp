<?php

namespace App\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class IsolatedDatabaseWebTestCase extends WebTestCase
{
    private mixed $originalEnvDatabaseUrl;
    private mixed $originalServerDatabaseUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalEnvDatabaseUrl = $_ENV['DATABASE_URL'] ?? null;
        $this->originalServerDatabaseUrl = $_SERVER['DATABASE_URL'] ?? null;
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            if (null === $this->originalEnvDatabaseUrl) {
                unset($_ENV['DATABASE_URL']);
            } else {
                $_ENV['DATABASE_URL'] = $this->originalEnvDatabaseUrl;
            }
            if (null === $this->originalServerDatabaseUrl) {
                unset($_SERVER['DATABASE_URL']);
            } else {
                $_SERVER['DATABASE_URL'] = $this->originalServerDatabaseUrl;
            }
        }
    }

    protected function clientWithDatabase(): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();
        $entityManager = $this->entityManager();
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $client;
    }

    protected function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        assert($entityManager instanceof EntityManagerInterface);

        return $entityManager;
    }
}