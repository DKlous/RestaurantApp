<?php

namespace App\Tests\Controller;

use App\Entity\Table;
use PHPUnit\Framework\Attributes\DataProvider;

final class FloorPlanTest extends IsolatedDatabaseWebTestCase
{
    /** @return iterable<array{string, string, string, string}> */
    public static function languages(): iterable
    {
        yield ['nl', 'Binnen', 'Terras', 'zitplaatsen'];
        yield ['en', 'Inside', 'Terrace', 'seats'];
        yield ['de', 'Innenbereich', 'Terrasse', 'Sitzplätze'];
        yield ['fr', 'Intérieur', 'Terrasse', 'places'];
        yield ['it', 'Interno', 'Terrazza', 'posti'];
    }

    #[DataProvider('languages')]
    public function testFloorPlanShowsNumberedTablesAndSeats(string $locale, string $inside, string $outside, string $seats): void
    {
        $client = $this->clientWithDatabase();
        foreach ([1 => 2, 2 => 2, 3 => 4, 4 => 4, 5 => 6, 6 => 2] as $number => $capacity) {
            $this->entityManager()->persist(new Table('Tafel '.$number, $capacity));
        }
        $this->entityManager()->flush();

        $crawler = $client->request('GET', '/'.$locale.'/');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filterXPath('//svg[@class="floor-plan__drawing"]'));
        $tables = $crawler->filterXPath('//svg[@class="floor-plan__drawing"]//*[@data-table-number]');
        self::assertCount(6, $tables);
        $numbers = [];
        foreach ($tables as $table) {
            self::assertInstanceOf(\DOMElement::class, $table);
            $numbers[(int) $table->getAttribute('data-table-number')] = (int) $table->getAttribute('data-capacity');
        }
        self::assertSame([1 => 2, 2 => 2, 3 => 4, 4 => 4, 5 => 6, 6 => 2], $numbers);
        foreach ($numbers as $number => $capacity) {
            self::assertCount($capacity, $crawler->filterXPath('//svg[@class="floor-plan__drawing"]//*[@data-table-number="'.$number.'"]/g'));
        }
        self::assertCount(6, $crawler->filterXPath('//*[@class="floor-plan__key"]//li'));
        self::assertCount(4, $crawler->filterXPath('//*[@class="floor-plan__key"]/div[h3="'.$inside.'"]//li'));
        self::assertCount(2, $crawler->filterXPath('//*[@class="floor-plan__key"]/div[h3="'.$outside.'"]//li'));
        self::assertStringContainsString($inside, $crawler->filterXPath('//section[contains(concat(" ", normalize-space(@class), " "), " floor-plan ")]')->text());
        self::assertStringContainsString($outside, $crawler->filterXPath('//section[contains(concat(" ", normalize-space(@class), " "), " floor-plan ")]')->text());
        self::assertStringContainsString('6 '.$seats, $crawler->filterXPath('//section[contains(concat(" ", normalize-space(@class), " "), " floor-plan ")]')->text());
    }

    public function testFloorPlanUsesActiveUnambiguousTablesAndCurrentCapacity(): void
    {
        $client = $this->clientWithDatabase();
        $entityManager = $this->entityManager();
        $entityManager->persist(new Table('Tafel 1', 3));
        $inactive = new Table('Tafel 2', 2);
        $inactive->setActive(false);
        $entityManager->persist($inactive);
        $entityManager->persist(new Table('Tafel 3', 4));
        $entityManager->persist(new Table('Tafel 3', 6));
        $entityManager->persist(new Table('Other table', 4));
        $entityManager->flush();

        $crawler = $client->request('GET', '/en/');
        self::assertResponseIsSuccessful();
        $tables = $crawler->filterXPath('//svg[@class="floor-plan__drawing"]//*[@data-table-number]');
        self::assertCount(1, $tables);
        self::assertSame('1', $tables->first()->attr('data-table-number'));
        self::assertSame('3', $tables->first()->attr('data-capacity'));
        self::assertStringContainsString('3 seats', $crawler->filterXPath('//*[@class="floor-plan__key"]')->text());
        self::assertStringContainsString('Ask our team about other tables.', $crawler->filterXPath('//section[contains(concat(" ", normalize-space(@class), " "), " floor-plan ")]')->text());
    }

    public function testFloorPlanHandlesSingularAndInvalidCapacities(): void
    {
        $client = $this->clientWithDatabase();
        $entityManager = $this->entityManager();
        $entityManager->persist(new Table('Tafel 1', 1));
        $entityManager->persist(new Table('Tafel 2', 0));
        $entityManager->persist(new Table('Tafel 5', 11));
        $entityManager->flush();

        $crawler = $client->request('GET', '/en/');
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filterXPath('//svg[@class="floor-plan__drawing"]//*[@data-table-number]'));
        self::assertStringContainsString('1 seat', $crawler->filterXPath('//*[@class="floor-plan__key"]')->text());
        self::assertStringContainsString('11 seats', $crawler->filterXPath('//*[@class="floor-plan__key"]')->text());
        self::assertCount(0, $crawler->filterXPath('//svg[@class="floor-plan__drawing"]//*[@data-table-number="2"]'));
        self::assertCount(0, $crawler->filterXPath('//svg[@class="floor-plan__drawing"]//*[@data-table-number="5"]/g'));
    }

    public function testFloorPlanHasAnEmptyState(): void
    {
        $client = $this->clientWithDatabase();
        $crawler = $client->request('GET', '/en/');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filterXPath('//svg[@class="floor-plan__drawing"]'));
        self::assertStringContainsString('Ask our team about the tables.', $crawler->filterXPath('//section[contains(concat(" ", normalize-space(@class), " "), " floor-plan ")]')->text());
    }
}