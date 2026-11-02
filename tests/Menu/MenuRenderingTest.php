<?php

namespace App\Tests\Menu;

use App\Entity\MenuItem;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class MenuRenderingTest extends KernelTestCase
{
    public function testMenuRendersTranslationsAndDistinctAllergenStates(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $twig = $container->get(Environment::class);
        $translator = $container->get('translator');
        $requests = $container->get(RequestStack::class);
        self::assertInstanceOf(Environment::class, $twig);
        self::assertInstanceOf(RequestStack::class, $requests);
        self::assertInstanceOf(TranslatorInterface::class, $translator);
        self::assertInstanceOf(LocaleAwareInterface::class, $translator);

        $wine = new MenuItem('Huiswijn (glas)', 480);
        $wine->setCategory('Dranken');
        $wine->setDescription('Rood of wit');
        $salmon = new MenuItem('Gerookte zalm', 1290);
        $salmon->setAllergens(['fish', 'milk']);
        $empty = new MenuItem('Eigen <gerecht>', 500);
        $empty->setAllergens([]);
        $empty->setDietaryLabels(['vegetarian', 'vegan', 'halal', 'kosher', 'gluten_free']);
        $items = [$wine, $salmon, $empty];

        foreach (['nl' => 'Het menu', 'en' => 'The menu', 'de' => 'Die Speisekarte', 'fr' => 'La carte', 'it' => 'Il menu'] as $locale => $title) {
            $request = Request::create('/menu');
            $request->setLocale($locale);
            $requests->push($request);
            $translator->setLocale($locale);
            $html = $twig->render('menu/index.html.twig', ['items' => $items, 'groupedItems' => ['Dranken' => [$wine], 'Hoofdgerechten' => [$salmon, $empty]]]);
            $crawler = new Crawler($html);
            self::assertSame($title, trim($crawler->filterXPath('//h1')->text()));
            self::assertCount(3, $crawler->filterXPath('//li[contains(concat(" ", normalize-space(@class), " "), " menu-item ")]/details'));
            self::assertCount(3, $crawler->filterXPath('//li[contains(concat(" ", normalize-space(@class), " "), " menu-item ")]/details/summary//*[contains(concat(" ", normalize-space(@class), " "), " menu-item__name ")]'));
            self::assertCount(14, $crawler->filterXPath('//details[@class="menu-allergen-guide"]//li'));
            self::assertCount(1, $crawler->filterXPath('//nav[@class="menu-filter" and @hidden]'));
            $firstItem = $crawler->filterXPath('//li[contains(concat(" ", normalize-space(@class), " "), " menu-item ")]')->first();
            self::assertStringContainsString('18+', $firstItem->text());
            self::assertStringContainsString('en' === $locale ? '4.80' : '4,80', $firstItem->text());
            self::assertStringContainsString($translator->trans('menu.allergens_unknown', [], 'menu'), $html);
            self::assertStringContainsString($translator->trans('menu.allergens_none', [], 'menu'), $html);
            self::assertStringContainsString($translator->trans('allergen.fish', [], 'menu'), $html);
            self::assertStringContainsString($translator->trans('menu.allergen_advice', [], 'menu'), $html);
            self::assertCount(2, $firstItem->filterXPath('.//section[contains(concat(" ", normalize-space(@class), " "), " menu-item__section ")]'));
            self::assertCount(0, $firstItem->filterXPath('.//*[contains(concat(" ", normalize-space(@class), " "), " menu-item__diet ")]'));
            $labels = $crawler->filterXPath('//*[contains(concat(" ", normalize-space(@class), " "), " menu-item__diet ")]');
            self::assertCount(5, $labels);
            foreach (['vegetarian', 'vegan', 'halal', 'kosher', 'gluten_free'] as $index => $label) {
                self::assertSame($translator->trans('diet.'.$label, [], 'menu'), trim($labels->eq($index)->text()));
            }
            self::assertCount(0, $crawler->filterXPath('//*[contains(concat(" ", normalize-space(@class), " "), " menu-notice ") or contains(concat(" ", normalize-space(@class), " "), " menu-item__disclaimer ")]'));
            self::assertStringContainsString('Eigen &lt;gerecht&gt;', $html);
            if ('en' === $locale) {
                self::assertStringContainsString('House wine (glass)', $html);
                self::assertStringContainsString('Smoked salmon', $html);
                self::assertStringContainsString('Red or white', $html);
            }
            $requests->pop();
        }
    }

    public function testPizzaDimensionsAreVisibleOnTheClickableSummary(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $twig = $container->get(Environment::class);
        $requests = $container->get(RequestStack::class);
        $translator = $container->get('translator');
        self::assertInstanceOf(Environment::class, $twig);
        self::assertInstanceOf(RequestStack::class, $requests);
        self::assertInstanceOf(TranslatorInterface::class, $translator);
        self::assertInstanceOf(LocaleAwareInterface::class, $translator);
        $pizza = new MenuItem('Pizza margherita', 1295);
        $pizza->setCategory('Pizza');
        $pizza->setDescription('Pizzabodem (tarwebloem, water, gist, zout, olijfolie), tomaat, mozzarella en basilicum · Ø 30 cm · bodemdikte 0,5 cm');
        $custom = new MenuItem('Eigen pizza', 1000);
        $custom->setCategory('Pizza');
        $custom->setDescription('Eigen recept');
        foreach (['nl', 'en', 'de', 'fr', 'it'] as $locale) {
            $request = Request::create('/menu');
            $request->setLocale($locale);
            $requests->push($request);
            $translator->setLocale($locale);
            $html = $twig->render('menu/index.html.twig', ['items' => [$pizza, $custom], 'groupedItems' => ['Pizza' => [$pizza, $custom]]]);
            $summaries = (new Crawler($html))->filterXPath('//li[contains(concat(" ", normalize-space(@class), " "), " menu-item ")]/details/summary');
            self::assertCount(2, $summaries);
            self::assertStringContainsString($translator->trans('Pizza margherita', [], 'menu'), $summaries->eq(0)->text());
            self::assertStringContainsString('Ø 30 cm', $summaries->eq(0)->text());
            self::assertStringContainsString('en' === $locale ? '0.5 cm' : '0,5 cm', $summaries->eq(0)->text());
            self::assertStringContainsString($translator->trans('menu.pizza_dimensions_unknown', [], 'menu'), $summaries->eq(1)->text());
            $requests->pop();
        }
    }
}