<?php

namespace App\Tests\Menu;

use App\Entity\MenuItem;
use App\Tests\Controller\IsolatedDatabaseWebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class MenuGroupingTest extends IsolatedDatabaseWebTestCase
{
    public function testDrinksAreCombinedAndOrderedWithAlcoholLast(): void
    {
        $client = $this->clientWithDatabase();
        $items = [
            ['Huiswijn (glas)', 'Dranken', false],
            ['Bier (fles)', 'Koffie & dranken', true],
            ['Cola', 'Koffie & dranken', false],
            ['Appelsap', 'Dranken', false],
            ['Bruisend water', 'Koffie & dranken', false],
            ['Thee', 'Dranken', false],
            ['Espresso', 'Koffie & dranken', false],
            ['Latte macchiato', 'Koffie & dranken', false],
            ['Cappuccino', 'Dranken', false],
            ['Eigen gerecht', 'Snacks', false],
        ];
        foreach ($items as [$name, $category, $alcoholic]) {
            $item = new MenuItem($name, 250);
            $item->setCategory($category);
            $item->setAlcoholic($alcoholic);
            $this->entityManager()->persist($item);
        }
        $this->entityManager()->flush();

        $crawler = $client->request('GET', '/nl/menu');
        self::assertResponseIsSuccessful();
        $drinks = $crawler->filterXPath('//section[contains(concat(" ", normalize-space(@class), " "), " menu-section ")][h2[normalize-space(.)="Dranken"]]');
        self::assertCount(1, $drinks);
        self::assertCount(0, $crawler->filterXPath('//section[contains(concat(" ", normalize-space(@class), " "), " menu-section ")]/h2[normalize-space(.)="Koffie & dranken"]'));
        $names = $drinks->filterXPath('.//summary//*[contains(concat(" ", normalize-space(@class), " "), " menu-item__name ")]')
            ->each(static fn (Crawler $name): string => trim($name->getNode(0)?->childNodes->item(1)->textContent ?? ''));
        self::assertSame([
            'Cappuccino', 'Latte macchiato', 'Espresso', 'Thee', 'Bruisend water',
            'Appelsap', 'Cola', 'Bier (fles)', 'Huiswijn (glas)',
        ], $names);
        self::assertCount(10, $crawler->filterXPath('//section[contains(concat(" ", normalize-space(@class), " "), " menu-section ")]//li[contains(concat(" ", normalize-space(@class), " "), " menu-item ")]'));
    }
}