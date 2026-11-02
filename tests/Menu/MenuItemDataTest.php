<?php

namespace App\Tests\Menu;

use App\Command\SeedDemoDataCommand;
use App\Entity\MenuItem;
use App\Migrations\Version20261102173000;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MariaDB1010Platform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class MenuItemDataTest extends TestCase
{
    public function testMigrationAndDoctrinePreserveNullEmptyAndExplicitLists(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 2).'/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $em = new EntityManager($connection, $config);
        (new SchemaTool($em))->createSchema([$em->getClassMetadata(MenuItem::class)]);
        $connection->executeStatement('ALTER TABLE menu_item DROP COLUMN dietary_labels');
        $connection->executeStatement("INSERT INTO menu_item (name, price_cents, is_active, is_alcoholic) VALUES ('Bestaand', 100, 1, 0)");

        $migration = new Version20261102173000($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
        $existing = $em->find(MenuItem::class, 1);
        self::assertInstanceOf(MenuItem::class, $existing);
        self::assertNull($existing->getDietaryLabels());
        self::assertNull($existing->getAllergens());

        $empty = new MenuItem('Leeg', 200);
        $empty->setDietaryLabels([]);
        $empty->setAllergens([]);
        $explicit = new MenuItem('Claims', 300);
        $explicit->setDietaryLabels(['vegetarian', 'vegan', 'halal', 'kosher', 'gluten_free']);
        $explicit->setAllergens(['milk', 'nuts']);
        $em->persist($empty);
        $em->persist($explicit);
        $em->flush();
        $emptyId = $empty->getId();
        $explicitId = $explicit->getId();
        $em->clear();

        $empty = $em->find(MenuItem::class, $emptyId);
        $explicit = $em->find(MenuItem::class, $explicitId);
        self::assertInstanceOf(MenuItem::class, $empty);
        self::assertInstanceOf(MenuItem::class, $explicit);
        self::assertSame([], $empty->getDietaryLabels());
        self::assertSame([], $empty->getAllergens());
        self::assertSame(['vegetarian', 'vegan', 'halal', 'kosher', 'gluten_free'], $explicit->getDietaryLabels());
        self::assertSame(['milk', 'nuts'], $explicit->getAllergens());

        $down = new Version20261102173000($connection, new NullLogger());
        $down->down(new Schema());
        foreach ($down->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
        self::assertSame('Bestaand', $connection->fetchOne('SELECT name FROM menu_item WHERE id = 1'));
        self::assertArrayNotHasKey('dietary_labels', $connection->createSchemaManager()->listTableColumns('menu_item'));
    }

    public function testMigrationUsesMariaDbJsonWithoutConnecting(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '11.4.0-MariaDB']);
        self::assertInstanceOf(MariaDB1010Platform::class, $connection->getDatabasePlatform());
        self::assertSame('JSON', $connection->getDatabasePlatform()->getJsonTypeDeclarationSQL([]));
        $migration = new Version20261102173000($connection, new NullLogger());
        $migration->up(new Schema());
        self::assertSame('ALTER TABLE menu_item ADD dietary_labels JSON DEFAULT NULL', $migration->getSql()[0]->getStatement());
        self::assertFalse($connection->isConnected());
    }

    public function testSeedPersistsAndRemainsIdempotentAfterReloading(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 2).'/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $em = new EntityManager($connection, $config);
        $metadata = $em->getClassMetadata(MenuItem::class);
        (new SchemaTool($em))->createSchema([$metadata]);

        $water = new MenuItem('Bruisend water', 275);
        $water->setCategory('Dranken');
        $water->setDescription('Bruisend mineraalwater');
        $custom = new MenuItem('Eigen gerecht', 500);
        $custom->setAllergens([]);
        $custom->setDietaryLabels(['halal']);
        $fries = new MenuItem('Friet met mayonaise', 450);
        $fries->setCategory('Snacks');
        $fries->setDescription('Aardappel, plantaardige olie en mayonaise (plantaardige olie, eigeel, mosterd, azijn, zout)');
        $fries->setAllergens(['eggs', 'mustard']);
        $em->persist($water);
        $em->persist($custom);
        $em->persist($fries);
        $em->flush();
        $waterId = $water->getId();
        $customId = $custom->getId();
        $friesId = $fries->getId();
        $em->clear();

        $repository = new EntityRepository($em, $metadata);
        $seedEm = $this->createMock(EntityManagerInterface::class);
        $seedEm->method('getRepository')->with(MenuItem::class)->willReturn($repository);
        $seedEm->method('persist')->willReturnCallback(static function (object $item) use ($em): void {
            $em->persist($item);
        });
        $command = new SeedDemoDataCommand($seedEm, $this->createMock(UserPasswordHasherInterface::class));
        $seedMenu = new \ReflectionMethod($command, 'seedMenu');
        $seedMenu->invoke($command);
        $em->flush();
        $em->clear();

        $water = $em->find(MenuItem::class, $waterId);
        $custom = $em->find(MenuItem::class, $customId);
        $fries = $em->find(MenuItem::class, $friesId);
        self::assertInstanceOf(MenuItem::class, $water);
        self::assertInstanceOf(MenuItem::class, $custom);
        self::assertInstanceOf(MenuItem::class, $fries);
        self::assertSame([], $water->getAllergens());
        self::assertSame(['vegetarian', 'vegan', 'gluten_free'], $water->getDietaryLabels());
        self::assertSame([], $custom->getAllergens());
        self::assertSame(['halal'], $custom->getDietaryLabels());
        self::assertSame(['eggs', 'mustard'], $fries->getAllergens());
        self::assertSame(['vegetarian'], $fries->getDietaryLabels());
        $snacks = $repository->findBy(['name' => 'Groentesticks met hummus']);
        self::assertCount(1, $snacks);
        self::assertSame(['sesame'], $snacks[0]->getAllergens());
        self::assertSame(['vegetarian', 'vegan'], $snacks[0]->getDietaryLabels());
        $water->setDietaryLabels([]);
        $em->flush();
        $em->clear();

        $before = $connection->fetchAllAssociative('SELECT * FROM menu_item ORDER BY id');
        $seedMenu->invoke($command);
        $em->flush();
        $em->clear();
        self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM menu_item ORDER BY id'));
    }
}