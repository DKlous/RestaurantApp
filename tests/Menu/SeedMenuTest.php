<?php

namespace App\Tests\Menu;

use App\Command\SeedDemoDataCommand;
use App\Entity\MenuItem;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SeedMenuTest extends TestCase
{
    public function testMenuSeedingPreservesExistingItemsAndIsRepeatable(): void
    {
        $existing = new MenuItem('Bitterballen', 450);
        $existing->setCategory('Voorgerechten');
        $existing->setDescription('Klassiek, 15 stuks');
        $existing->setActive(false);
        $custom = new MenuItem('Eigen gerecht', 1234);
        $items = [$existing, $custom];

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findAll')->willReturnCallback(static function () use (&$items): array {
            return $items;
        });
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(MenuItem::class)->willReturn($repository);
        $em->method('persist')->willReturnCallback(static function (object $item) use (&$items): void {
            self::assertInstanceOf(MenuItem::class, $item);
            $items[] = $item;
        });
        $command = new SeedDemoDataCommand($em, $this->createMock(UserPasswordHasherInterface::class));
        $seedMenu = new \ReflectionMethod($command, 'seedMenu');
        $seedMenu->invoke($command);
        $count = count($items);
        $seedMenu->invoke($command);

        self::assertCount($count, $items);
        self::assertSame(450, $existing->getPriceCents());
        self::assertSame('Voorgerechten', $existing->getCategory());
        self::assertSame('Klassiek, 15 stuks', $existing->getDescription());
        self::assertFalse($existing->isActive());
        self::assertContains($custom, $items);
        $byName = [];
        foreach ($items as $item) {
            $byName[$item->getName()] = $item;
        }
        self::assertCount($count, $byName);
        self::assertArrayHasKey('Kinderpizza margherita', $byName);
        $childPizzaDescription = $byName['Kinderpizza margherita']->getDescription();
        self::assertNotNull($childPizzaDescription);
        self::assertStringContainsString('Ø 20 cm', $childPizzaDescription);
        self::assertArrayHasKey('Griekse salade', $byName);
        self::assertTrue($byName['Bier (fles)']->isAlcoholic());
        self::assertSame(['milk'], $byName['Griekse salade']->getAllergens());
        self::assertArrayHasKey('Latte macchiato', $byName);
        self::assertArrayHasKey('Pizza tonno', $byName);
        self::assertSame(['gluten', 'milk', 'fish'], $byName['Pizza tonno']->getAllergens());
        self::assertSame(['milk'], $byName['Latte macchiato']->getAllergens());
        foreach ($items as $item) {
            if ('Pizza' === $item->getCategory()) {
                self::assertMatchesRegularExpression('/Ø \d+ cm · bodemdikte \d+,\d+ cm/', (string) $item->getDescription(), $item->getName());
                self::assertStringContainsString('tarwebloem', (string) $item->getDescription());
                self::assertContains('gluten', $item->getAllergens() ?? []);
                self::assertContains('milk', $item->getAllergens() ?? []);
            }
        }

        foreach (['en', 'de', 'fr', 'it'] as $locale) {
            /** @var array<string, string> $catalogue */
            $catalogue = require dirname(__DIR__, 2).'/translations/menu.'.$locale.'.php';
            foreach (['Tartaletjes', 'Runderbrula', 'Gerookte zalm', 'Rijsttafel', 'Huiswijn (glas)', 'Koffie / thee', 'Drie seizoens-tartaletjes', 'Met jus en aardappelfrites', 'Komkomper en dille', 'Klassiek, 8 gangen', 'Rood of wit', 'Klassiek'] as $text) {
                self::assertArrayHasKey($text, $catalogue, $locale.' mist: '.$text);
            }
            foreach ($items as $item) {
                if ($item === $custom) {
                    continue;
                }
                foreach ([$item->getName(), $item->getCategory(), $item->getDescription()] as $text) {
                    if (null !== $text) {
                        self::assertArrayHasKey($text, $catalogue, $locale.' mist: '.$text);
                    }
                }
            }
        }
    }

    public function testRecognizedLegacyDemoItemsAreUpdatedWithoutChangingPrices(): void
    {
        $definitions = [
            ['Pizza margherita', 'Pizza', 'Tomaat, mozzarella en basilicum', 1295],
            ['Pizza salami', 'Pizza', 'Tomaat, mozzarella en salami', 1495],
            ['Pizza quattro formaggi', 'Pizza', 'Tomaat, mozzarella, gorgonzola, Parmezaanse kaas en geitenkaas · Ø 30 cm · dunne bodem', 1595],
            ['Pizza vegetariana', 'Pizza', 'Tomaat, mozzarella, paprika, champignons en courgette · Ø 30 cm · dunne bodem', 1495],
            ['Pizza funghi', 'Pizza', 'Tomaat, mozzarella en champignons · Ø 30 cm · dunne bodem', 1395],
            ['Kinderpizza margherita', 'Pizza', 'Tomaat, mozzarella en basilicum · Ø 20 cm · dunne bodem', 795],
            ['Panini caprese', 'Panini', 'Mozzarella, tomaat en pesto', 895],
            ['Friet met mayonaise', 'Snacks', 'Vers gebakken friet', 450],
            ['Pasta pesto', 'Pasta', 'Pesto, Parmezaan en rucola', 1395],
            ['Lasagne bolognese', 'Pasta', 'Uit de oven met tomaat en kaas', 1495],
            ['Griekse salade', 'Salades', 'Tomaat, komkommer, feta, olijven en rode ui', 1095],
            ['Koffie / thee', 'Dranken', 'Klassiek', 320],
            ['Gerookte zalm', 'Hoofdgerechten', 'Komkomper en dille', 1290],
        ];
        $items = [];
        foreach ($definitions as [$name, $category, $description, $price]) {
            $item = new MenuItem($name, $price);
            $item->setCategory($category);
            $item->setDescription($description);
            $items[] = $item;
        }
        $this->seed($items);
        $count = count($items);
        $snapshot = $this->snapshot($items);
        $this->seed($items);
        self::assertCount($count, $items);
        self::assertSame($snapshot, $this->snapshot($items));
        $byName = [];
        foreach ($items as $item) {
            $byName[$item->getName()] = $item;
        }
        foreach ($definitions as [$name, , , $price]) {
            self::assertSame($price, $byName[$name]->getPriceCents(), $name);
        }
        self::assertFalse($byName['Koffie / thee']->isActive());
        self::assertTrue($byName['Espresso']->isActive());
        self::assertTrue($byName['Thee']->isActive());
        self::assertNull($byName['Gerookte zalm']->getAllergens());
        self::assertSame('Komkomper en dille', $byName['Gerookte zalm']->getDescription());
        self::assertSame(['gluten', 'milk', 'nuts'], $byName['Panini caprese']->getAllergens());
        self::assertSame('Vers gebakken friet', $byName['Friet met mayonaise']->getDescription());
        self::assertNull($byName['Friet met mayonaise']->getAllergens());
        self::assertNull($byName['Friet met mayonaise']->getDietaryLabels());
        self::assertSame(['gluten', 'eggs', 'milk'], $byName['Lasagne bolognese']->getAllergens());
        foreach ($definitions as [$name, $category]) {
            if ('Pizza' === $category) {
                self::assertMatchesRegularExpression('/Ø \d+ cm · bodemdikte \d+,\d+ cm/', (string) $byName[$name]->getDescription(), $name);
                self::assertSame(['gluten', 'milk'], $byName[$name]->getAllergens());
            }
        }
    }

    public function testCustomizedItemsAreNeverMistakenForDemoData(): void
    {
        $pizza = new MenuItem('Pizza margherita', 1295);
        $pizza->setCategory('Pizza');
        $pizza->setDescription('Eigen recept');
        $coffee = new MenuItem('Koffie / thee', 321);
        $coffee->setCategory('Dranken');
        $coffee->setDescription('Klassiek');
        $salad = new MenuItem('Griekse salade', 1095);
        $salad->setCategory('Salades');
        $salad->setDescription('Tomaat, komkommer, feta, olijven en rode ui');
        $salad->setAllergens(['mustard']);
        $pricedPizza = new MenuItem('Pizza salami', 1496);
        $pricedPizza->setCategory('Pizza');
        $pricedPizza->setDescription('Tomaat, mozzarella en salami');
        $inactivePizza = new MenuItem('Pizza funghi', 1395);
        $inactivePizza->setCategory('Pizza');
        $inactivePizza->setDescription('Tomaat, mozzarella en champignons · Ø 30 cm · dunne bodem');
        $inactivePizza->setActive(false);
        $alcoholicPizza = new MenuItem('Pizza margherita', 1295);
        $alcoholicPizza->setCategory('Pizza');
        $alcoholicPizza->setDescription('Tomaat, mozzarella en basilicum');
        $alcoholicPizza->setAlcoholic(true);
        $items = [$pizza, $coffee, $salad, $pricedPizza, $inactivePizza, $alcoholicPizza];
        $this->seed($items);
        $count = count($items);
        $this->seed($items);
        self::assertCount($count, $items);
        self::assertSame('Eigen recept', $pizza->getDescription());
        self::assertNull($pizza->getAllergens());
        self::assertTrue($coffee->isActive());
        self::assertSame(321, $coffee->getPriceCents());
        self::assertSame('Tomaat, komkommer, feta, olijven en rode ui', $salad->getDescription());
        self::assertSame(['mustard'], $salad->getAllergens());
        self::assertSame('Tomaat, mozzarella en salami', $pricedPizza->getDescription());
        self::assertSame(1496, $pricedPizza->getPriceCents());
        self::assertNull($pricedPizza->getAllergens());
        self::assertSame('Tomaat, mozzarella en champignons · Ø 30 cm · dunne bodem', $inactivePizza->getDescription());
        self::assertFalse($inactivePizza->isActive());
        self::assertSame('Tomaat, mozzarella en basilicum', $alcoholicPizza->getDescription());
        self::assertNull($alcoholicPizza->getAllergens());
        self::assertTrue($alcoholicPizza->isAlcoholic());
    }

    public function testDemoRecipesListIngredientsAndRecognizableAllergens(): void
    {
        $items = [];
        $this->seed($items);
        $byName = [];
        foreach ($items as $item) {
            $byName[$item->getName()] = $item;
        }
        $recipes = [
            'Panini caprese' => [['tarwebloem', 'mozzarella', 'tomaat', 'cashewnoten'], ['gluten', 'milk', 'nuts']],
            'Panini kip pesto' => [['tarwebloem', 'kip', 'rucola', 'cashewnoten'], ['gluten', 'milk', 'nuts']],
            'Panini ham en kaas' => [['tarwebloem', 'ham', 'kaas'], ['gluten', 'milk']],
            'Bitterballen' => [['rundvlees', 'tarwebloem', 'melk', 'ei', 'mosterd'], ['gluten', 'milk', 'eggs', 'mustard']],
            'Friet met mayonaise' => [['Aardappel', 'eigeel', 'mosterd'], ['eggs', 'mustard']],
            'Pasta pesto' => [['tarwebloem', 'cashewnoten', 'Parmezaanse kaas', 'rucola'], ['gluten', 'milk', 'nuts']],
            'Lasagne bolognese' => [['tarwebloem', 'ei', 'rundergehakt', 'melk'], ['gluten', 'eggs', 'milk']],
            'Griekse salade' => [['Tomaat', 'komkommer', 'feta', 'olijven', 'rode ui'], ['milk']],
            'Pizza tonno' => [['tarwebloem', 'tomaat', 'mozzarella', 'tonijn', 'rode ui'], ['gluten', 'milk', 'fish']],
            'Latte macchiato' => [['Koffiebonen', 'water', 'melk'], ['milk']],
        ];
        foreach ($recipes as $name => [$ingredients, $allergens]) {
            self::assertArrayHasKey($name, $byName);
            foreach ($ingredients as $ingredient) {
                self::assertStringContainsString($ingredient, (string) $byName[$name]->getDescription(), $name);
            }
            self::assertSame($allergens, $byName[$name]->getAllergens(), $name);
        }
        self::assertSame([], $byName['Espresso']->getAllergens());
        self::assertSame([], $byName['Thee']->getAllergens());
    }

    public function testDemoDietaryClaimsAndAllergenAbsenceAreConservative(): void
    {
        $items = [];
        $this->seed($items);
        $this->seed($items);
        $claims = [
            'Friet met mayonaise' => ['vegetarian'],
            'Groentesticks met hummus' => ['vegetarian', 'vegan'],
            'Espresso' => ['vegetarian', 'vegan'],
            'Thee' => ['vegetarian', 'vegan'],
            'Cappuccino' => ['vegetarian'],
            'Latte macchiato' => ['vegetarian'],
            'Bruisend water' => ['vegetarian', 'vegan', 'gluten_free'],
        ];
        $byName = [];
        foreach ($items as $item) {
            $byName[$item->getName()] = $item;
            self::assertSame($claims[$item->getName()] ?? null, $item->getDietaryLabels(), $item->getName());
            self::assertNotContains('halal', $item->getDietaryLabels() ?? [], $item->getName());
            self::assertNotContains('kosher', $item->getDietaryLabels() ?? [], $item->getName());
        }
        foreach (['Bruisend water', 'Espresso', 'Thee'] as $name) {
            self::assertSame([], $byName[$name]->getAllergens(), $name);
        }
        foreach (['Cola', 'Appelsap', 'Rode wijn (glas)', 'Witte wijn (glas)'] as $name) {
            self::assertNull($byName[$name]->getAllergens(), $name);
            self::assertNull($byName[$name]->getDietaryLabels(), $name);
        }
        foreach (['Pizza vegetariana', 'Pizza margherita', 'Panini caprese', 'Pasta pesto', 'Griekse salade', 'Bier (fles)'] as $name) {
            self::assertNull($byName[$name]->getDietaryLabels(), $name);
        }
        self::assertSame(['gluten'], $byName['Bier (fles)']->getAllergens());
    }

    public function testNewAndMatchingDemoFriesReceiveVegetarianClaim(): void
    {
        $description = 'Aardappel, plantaardige olie en mayonaise (plantaardige olie, eigeel, mosterd, azijn, zout)';
        $items = [];
        $this->seed($items);
        $fries = array_values(array_filter($items, static fn (MenuItem $item): bool => 'Friet met mayonaise' === $item->getName()));
        self::assertCount(1, $fries);
        self::assertSame('Snacks', $fries[0]->getCategory());
        self::assertSame($description, $fries[0]->getDescription());
        self::assertSame(450, $fries[0]->getPriceCents());
        self::assertSame(['eggs', 'mustard'], $fries[0]->getAllergens());
        self::assertSame(['vegetarian'], $fries[0]->getDietaryLabels());
        $snapshot = $this->snapshot($items);
        $this->seed($items);
        self::assertSame($snapshot, $this->snapshot($items));

        $existing = new MenuItem('Friet met mayonaise', 450);
        $existing->setCategory('Snacks');
        $existing->setDescription($description);
        $existing->setAllergens(['eggs', 'mustard']);
        $items = [$existing];
        $this->seed($items);
        self::assertSame(['eggs', 'mustard'], $existing->getAllergens());
        self::assertSame(['vegetarian'], $existing->getDietaryLabels());
        $snapshot = $this->snapshot($items);
        $this->seed($items);
        self::assertSame($snapshot, $this->snapshot($items));
    }

    public function testExistingFriesWithDifferentDataOrDuplicateNamesArePreserved(): void
    {
        $description = 'Aardappel, plantaardige olie en mayonaise (plantaardige olie, eigeel, mosterd, azijn, zout)';
        $cases = [
            ['Vers gebakken friet', 450, 'Snacks', true, false, null, null],
            [$description.', spek', 450, 'Snacks', true, false, ['eggs', 'mustard'], null],
            [$description, 451, 'Snacks', true, false, ['eggs', 'mustard'], null],
            [$description, 450, 'Bijgerechten', true, false, ['eggs', 'mustard'], null],
            [$description, 450, 'Snacks', false, false, ['eggs', 'mustard'], null],
            [$description, 450, 'Snacks', true, true, ['eggs', 'mustard'], null],
            [$description, 450, 'Snacks', true, false, null, null],
            [$description, 450, 'Snacks', true, false, [], null],
            [$description, 450, 'Snacks', true, false, ['eggs', 'mustard', 'milk'], null],
            [$description, 450, 'Snacks', true, false, ['eggs', 'mustard'], []],
            [$description, 450, 'Snacks', true, false, ['eggs', 'mustard'], ['halal']],
        ];
        foreach ($cases as [$recipe, $price, $category, $active, $alcoholic, $allergens, $labels]) {
            $existing = new MenuItem('Friet met mayonaise', $price);
            $existing->setCategory($category);
            $existing->setDescription($recipe);
            $existing->setActive($active);
            $existing->setAlcoholic($alcoholic);
            $existing->setAllergens($allergens);
            $existing->setDietaryLabels($labels);
            $items = [$existing];
            $before = $this->snapshot($items);
            $this->seed($items);
            $snapshot = $this->snapshot($items);
            $this->seed($items);
            self::assertSame($before, $this->snapshot([$existing]));
            self::assertSame($snapshot, $this->snapshot($items));
            self::assertCount(1, array_filter($items, static fn (MenuItem $item): bool => 'Friet met mayonaise' === $item->getName()));
        }

        $first = new MenuItem('Friet met mayonaise', 450);
        $first->setCategory('Snacks');
        $first->setDescription($description);
        $first->setAllergens(['eggs', 'mustard']);
        $second = clone $first;
        $items = [$first, $second];
        $before = $this->snapshot($items);
        $this->seed($items);
        self::assertSame($before, $this->snapshot([$first, $second]));
        self::assertCount(2, array_filter($items, static fn (MenuItem $item): bool => 'Friet met mayonaise' === $item->getName()));
    }

    public function testVeganSnackIsSeededWithSafeClaimsAndIsRepeatable(): void
    {
        $items = [];
        $this->seed($items);
        $snapshot = $this->snapshot($items);
        $this->seed($items);

        self::assertSame($snapshot, $this->snapshot($items));
        $snacks = array_values(array_filter($items, static fn (MenuItem $item): bool => 'Groentesticks met hummus' === $item->getName()));
        self::assertCount(1, $snacks);
        $snack = $snacks[0];
        self::assertSame('Snacks', $snack->getCategory());
        self::assertSame('Wortel, komkommer en hummus (kikkererwten, tahin (sesam), citroen, knoflook en olijfolie)', $snack->getDescription());
        self::assertSame(650, $snack->getPriceCents());
        self::assertTrue($snack->isActive());
        self::assertFalse($snack->isAlcoholic());
        self::assertSame(['sesame'], $snack->getAllergens());
        self::assertSame(['vegetarian', 'vegan'], $snack->getDietaryLabels());
    }

    public function testExistingVeganSnackDataIsPreserved(): void
    {
        $description = 'Wortel, komkommer en hummus (kikkererwten, tahin (sesam), citroen, knoflook en olijfolie)';
        $cases = [
            ['Eigen recept met yoghurt', 650, ['milk'], null],
            ['Eigen recept met yoghurt', 650, ['sesame'], null],
            [$description, 651, ['sesame'], null],
            [$description, 650, null, null],
            [$description, 650, [], null],
            [$description, 650, ['milk'], null],
            [$description, 650, ['sesame'], []],
            [$description, 650, ['sesame'], ['halal']],
        ];
        foreach ($cases as [$recipe, $price, $allergens, $labels]) {
            $custom = new MenuItem('Groentesticks met hummus', $price);
            $custom->setCategory('Snacks');
            $custom->setDescription($recipe);
            $custom->setAllergens($allergens);
            $custom->setDietaryLabels($labels);
            $items = [$custom];
            $before = $this->snapshot($items);
            $this->seed($items);
            $snapshot = $this->snapshot($items);
            $this->seed($items);

            self::assertSame($before, $this->snapshot([$custom]));
            self::assertSame($snapshot, $this->snapshot($items));
            self::assertCount(1, array_filter($items, static fn (MenuItem $item): bool => 'Groentesticks met hummus' === $item->getName()));
        }
    }

    public function testMatchingVeganSnackWithKnownAllergensReceivesClaims(): void
    {
        $snack = new MenuItem('Groentesticks met hummus', 650);
        $snack->setCategory('Snacks');
        $snack->setDescription('Wortel, komkommer en hummus (kikkererwten, tahin (sesam), citroen, knoflook en olijfolie)');
        $snack->setAllergens(['sesame']);
        $items = [$snack];
        $this->seed($items);
        self::assertSame(['sesame'], $snack->getAllergens());
        self::assertSame(['vegetarian', 'vegan'], $snack->getDietaryLabels());
        $snapshot = $this->snapshot($items);
        $this->seed($items);
        self::assertSame($snapshot, $this->snapshot($items));
    }

    public function testExistingVeganSnackWithUnknownAllergensOrDuplicateNamesIsNotEnriched(): void
    {
        $unknown = new MenuItem('Groentesticks met hummus', 650);
        $unknown->setCategory('Snacks');
        $unknown->setDescription('Wortel, komkommer en hummus (kikkererwten, tahin (sesam), citroen, knoflook en olijfolie)');
        $items = [$unknown];
        $this->seed($items);
        self::assertNull($unknown->getAllergens());
        self::assertNull($unknown->getDietaryLabels());

        $duplicate = new MenuItem('Groentesticks met hummus', 650);
        $duplicate->setCategory('Snacks');
        $duplicate->setDescription($unknown->getDescription());
        $duplicate->setAllergens(['sesame']);
        $unknown->setAllergens(['sesame']);
        $items[] = $duplicate;
        $before = $this->snapshot($items);
        $this->seed($items);
        self::assertSame($before, $this->snapshot($items));
        self::assertNull($unknown->getDietaryLabels());
        self::assertNull($duplicate->getDietaryLabels());
    }

    public function testExistingDemoDataIsEnrichedOnlyIfItsEntireFingerprintIsUnchanged(): void
    {
        $water = new MenuItem('Bruisend water', 275);
        $water->setCategory('Dranken');
        $water->setDescription('Bruisend mineraalwater');

        $espresso = new MenuItem('Espresso', 250);
        $espresso->setCategory('Koffie & dranken');
        $espresso->setDescription('Koffiebonen en water');

        $latte = new MenuItem('Latte macchiato', 395);
        $latte->setCategory('Koffie & dranken');
        $latte->setDescription('Koffiebonen, water en warme melk');
        $latte->setAllergens(['milk']);

        $items = [$water, $espresso, $latte];
        $this->seed($items);
        self::assertSame([], $water->getAllergens());
        self::assertSame(['vegetarian', 'vegan', 'gluten_free'], $water->getDietaryLabels());
        self::assertSame([], $espresso->getAllergens());
        self::assertSame(['vegetarian', 'vegan'], $espresso->getDietaryLabels());
        self::assertSame(['milk'], $latte->getAllergens());
        self::assertSame(['vegetarian'], $latte->getDietaryLabels());
        $snapshot = $this->snapshot($items);
        $this->seed($items);
        self::assertSame($snapshot, $this->snapshot($items));
    }

    public function testExistingDietaryAndAllergenDataIsNeverOverwritten(): void
    {
        $water = new MenuItem('Bruisend water', 275);
        $water->setCategory('Dranken');
        $water->setDescription('Bruisend mineraalwater');
        $water->setDietaryLabels([]);

        $espresso = new MenuItem('Espresso', 250);
        $espresso->setCategory('Koffie & dranken');
        $espresso->setDescription('Koffiebonen en water');
        $espresso->setAllergens(['milk']);

        $latte = new MenuItem('Latte macchiato', 395);
        $latte->setCategory('Koffie & dranken');
        $latte->setDescription('Koffiebonen, water en warme melk');
        $latte->setAllergens(['milk']);
        $latte->setDietaryLabels(['halal']);

        $legacy = new MenuItem('Espresso', 250);
        $legacy->setCategory('Koffie & dranken');
        $legacy->setDescription('Vers gezet');
        $legacy->setDietaryLabels(['kosher']);

        $changed = new MenuItem('Thee', 275);
        $changed->setCategory('Koffie & dranken');
        $changed->setDescription('Theebladeren, water en melk');

        $items = [$water, $espresso, $latte, $legacy, $changed];
        $before = $this->snapshot($items);
        $this->seed($items);
        $this->seed($items);
        self::assertSame($before, $this->snapshot(array_slice($items, 0, 5)));
    }

    public function testLegacyClaimsAndAmbiguousDuplicatesAreHandledConservatively(): void
    {
        $legacyTea = new MenuItem('Thee', 275);
        $legacyTea->setCategory('Koffie & dranken');
        $legacyTea->setDescription('Keuze uit verschillende soorten thee');

        $coffee = new MenuItem('Koffie / thee', 320);
        $coffee->setCategory('Dranken');
        $coffee->setDescription('Klassiek');
        $coffee->setDietaryLabels([]);

        $water = new MenuItem('Bruisend water', 275);
        $water->setCategory('Dranken');
        $water->setDescription('Bruisend mineraalwater');
        $duplicate = new MenuItem('Bruisend water', 275);
        $duplicate->setCategory('Dranken');
        $duplicate->setDescription('Bruisend mineraalwater');

        $espresso = new MenuItem('Espresso', 250);
        $espresso->setCategory('Koffie & dranken');
        $espresso->setDescription('Koffiebonen en water');
        $espresso->setAllergens([]);

        $items = [$legacyTea, $coffee, $water, $duplicate, $espresso];
        $this->seed($items);
        self::assertSame('Keuze uit verschillende soorten thee', $legacyTea->getDescription());
        self::assertNull($legacyTea->getAllergens());
        self::assertNull($legacyTea->getDietaryLabels());
        self::assertTrue($coffee->isActive());
        self::assertSame([], $coffee->getDietaryLabels());
        self::assertNull($water->getAllergens());
        self::assertNull($water->getDietaryLabels());
        self::assertNull($duplicate->getAllergens());
        self::assertNull($duplicate->getDietaryLabels());
        self::assertSame([], $espresso->getAllergens());
        self::assertNull($espresso->getDietaryLabels());
        $snapshot = $this->snapshot($items);
        $this->seed($items);
        self::assertSame($snapshot, $this->snapshot($items));
    }

    public function testLegacyCoffeeRemainsActiveWhenAReplacementIsInactive(): void
    {
        $coffee = new MenuItem('Koffie / thee', 320);
        $coffee->setCategory('Dranken');
        $coffee->setDescription('Klassiek');
        $espresso = new MenuItem('Espresso', 250);
        $espresso->setCategory('Koffie & dranken');
        $espresso->setDescription('Vers gezet');
        $espresso->setActive(false);
        $items = [$coffee, $espresso];
        $this->seed($items);
        self::assertTrue($coffee->isActive());
        self::assertFalse($espresso->isActive());
        self::assertSame('Vers gezet', $espresso->getDescription());
    }

    public function testDuplicateLegacyCoffeeItemsRemainActive(): void
    {
        $coffee = new MenuItem('Koffie / thee', 320);
        $coffee->setCategory('Dranken');
        $coffee->setDescription('Klassiek');
        $duplicate = new MenuItem('Koffie / thee', 320);
        $duplicate->setCategory('Dranken');
        $duplicate->setDescription('Klassiek');
        $items = [$coffee, $duplicate];
        $this->seed($items);
        $this->seed($items);
        self::assertTrue($coffee->isActive());
        self::assertTrue($duplicate->isActive());
    }

    /** @param list<MenuItem> $items
     *  @return list<array{string, ?string, ?string, int, bool, bool, list<string>|null, list<string>|null}>
     */
    private function snapshot(array $items): array
    {
        return array_map(static fn (MenuItem $item): array => [
            $item->getName(), $item->getCategory(), $item->getDescription(), $item->getPriceCents(),
            $item->isActive(), $item->isAlcoholic(), $item->getAllergens(), $item->getDietaryLabels(),
        ], $items);
    }

    /** @param list<MenuItem> $items */
    private function seed(array &$items): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findAll')->willReturnCallback(static function () use (&$items): array {
            return $items;
        });
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(MenuItem::class)->willReturn($repository);
        $em->method('persist')->willReturnCallback(static function (object $item) use (&$items): void {
            self::assertInstanceOf(MenuItem::class, $item);
            $items[] = $item;
        });
        $command = new SeedDemoDataCommand($em, $this->createMock(UserPasswordHasherInterface::class));
        (new \ReflectionMethod($command, 'seedMenu'))->invoke($command);
    }
}