<?php

namespace App\Command;

use App\Entity\MenuItem;
use App\Entity\Table;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed', description: 'Vult demo-data aan (tafels, menu, één staff-lid). Kan herhaald worden.')]
class SeedDemoDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->seedTables();
        $this->seedMenu();
        $staff = $this->seedStaff();

        $this->em->flush();

        if (null !== $staff) {
            $io->writeln(sprintf('Nieuw staff-lid: %s', $staff['email']));
            $io->writeln(sprintf('Wachtwoord: %s', $staff['password']));
        }

        $io->success('Demo-data is aangevuld.');

        return Command::SUCCESS;
    }

    private function seedTables(): void
    {
        if (0 !== $this->em->getRepository(Table::class)->count()) {
            return;
        }

        /** @var list<array{0: string, 1: int}> $definitions */
        $definitions = [
            ['Tafel 1', 2],
            ['Tafel 2', 2],
            ['Tafel 3', 4],
            ['Tafel 4', 4],
            ['Tafel 5', 6],
            ['Tafel 6', 2],
        ];

        foreach ($definitions as $definition) {
            [$name, $capacity] = $definition;
            $this->em->persist(new Table($name, $capacity));
        }
    }

    private function seedMenu(): void
    {
        $itemsByName = [];
        foreach ($this->em->getRepository(MenuItem::class)->findAll() as $item) {
            $itemsByName[$item->getName()][] = $item;
        }

        // Identieke eigen items zijn niet van ongewijzigde demo-items te onderscheiden.
        /** @var list<array{0: string, 1: string, 2: string, 3: int, 4: bool, 5: list<string>|null, 6: string|null}> $definitions */
        $definitions = [
            ['Panini', 'Panini caprese', 'Paninibrood (tarwebloem, water, gist, zout), mozzarella, tomaat en pesto (basilicum, cashewnoten, Parmezaanse kaas, knoflook, olijfolie)', 895, false, ['gluten', 'milk', 'nuts'], 'Mozzarella, tomaat en pesto'],
            ['Panini', 'Panini kip pesto', 'Paninibrood (tarwebloem, water, gist, zout), kip, rucola en pesto (basilicum, cashewnoten, Parmezaanse kaas, knoflook, olijfolie)', 995, false, ['gluten', 'milk', 'nuts'], 'Kip, pesto en rucola'],
            ['Snacks', 'Bitterballen', 'Bitterballen (rundvlees, bouillon, tarwebloem, boter, melk, ei, paneermeel), plantaardige olie en mosterd', 750, false, ['gluten', 'milk', 'eggs', 'mustard'], 'Acht stuks met mosterd'],
            ['Snacks', 'Friet met mayonaise', 'Aardappel, plantaardige olie en mayonaise (plantaardige olie, eigeel, mosterd, azijn, zout)', 450, false, ['eggs', 'mustard'], 'Vers gebakken friet'],
            ['Snacks', 'Groentesticks met hummus', 'Wortel, komkommer en hummus (kikkererwten, tahin (sesam), citroen, knoflook en olijfolie)', 650, false, ['sesame'], null],
            ['Pizza', 'Pizza margherita', 'Pizzabodem (tarwebloem, water, gist, zout, olijfolie), tomaat, mozzarella en basilicum · Ø 30 cm · bodemdikte 0,5 cm', 1295, false, ['gluten', 'milk'], 'Tomaat, mozzarella en basilicum'],
            ['Pizza', 'Pizza salami', 'Pizzabodem (tarwebloem, water, gist, zout, olijfolie), tomaat, mozzarella en salami · Ø 30 cm · bodemdikte 0,5 cm', 1495, false, ['gluten', 'milk'], 'Tomaat, mozzarella en salami'],
            ['Pasta', 'Pasta pesto', 'Pasta (tarwebloem, water), pesto (basilicum, cashewnoten, Parmezaanse kaas, knoflook, olijfolie), Parmezaanse kaas en rucola', 1395, false, ['gluten', 'milk', 'nuts'], 'Pesto, Parmezaan en rucola'],
            ['Pasta', 'Lasagne bolognese', 'Lasagnebladen (tarwebloem, ei), rundergehakt, tomaat, ui, wortel, olijfolie, bechamelsaus (melk, boter, tarwebloem) en kaas', 1495, false, ['gluten', 'eggs', 'milk'], 'Uit de oven met tomaat en kaas'],
            ['Koffie & dranken', 'Espresso', 'Koffiebonen en water', 250, false, null, 'Vers gezet'],
            ['Koffie & dranken', 'Cappuccino', 'Koffiebonen, water en melk', 350, false, ['milk'], 'Espresso met melkschuim'],
            ['Koffie & dranken', 'Latte macchiato', 'Koffiebonen, water en warme melk', 395, false, ['milk'], null],
            ['Panini', 'Panini ham en kaas', 'Paninibrood (tarwebloem, water, gist, zout), ham en kaas', 925, false, ['gluten', 'milk'], 'Ham en kaas'],
            ['Pizza', 'Pizza quattro formaggi', 'Pizzabodem (tarwebloem, water, gist, zout, olijfolie), tomaat, mozzarella, gorgonzola, Parmezaanse kaas en geitenkaas · Ø 30 cm · bodemdikte 0,5 cm', 1595, false, ['gluten', 'milk'], 'Tomaat, mozzarella, gorgonzola, Parmezaanse kaas en geitenkaas · Ø 30 cm · dunne bodem'],
            ['Pizza', 'Pizza vegetariana', 'Pizzabodem (tarwebloem, water, gist, zout, olijfolie), tomaat, mozzarella, paprika, champignons en courgette · Ø 30 cm · bodemdikte 0,5 cm', 1495, false, ['gluten', 'milk'], 'Tomaat, mozzarella, paprika, champignons en courgette · Ø 30 cm · dunne bodem'],
            ['Pizza', 'Pizza funghi', 'Pizzabodem (tarwebloem, water, gist, zout, olijfolie), tomaat, mozzarella en champignons · Ø 30 cm · bodemdikte 0,5 cm', 1395, false, ['gluten', 'milk'], 'Tomaat, mozzarella en champignons · Ø 30 cm · dunne bodem'],
            ['Pizza', 'Pizza tonno', 'Pizzabodem (tarwebloem, water, gist, zout, olijfolie), tomaat, mozzarella, tonijn en rode ui · Ø 30 cm · bodemdikte 0,5 cm', 1545, false, ['gluten', 'milk', 'fish'], null],
            ['Pizza', 'Kinderpizza margherita', 'Pizzabodem (tarwebloem, water, gist, zout, olijfolie), tomaat, mozzarella en basilicum · Ø 20 cm · bodemdikte 0,5 cm', 795, false, ['gluten', 'milk'], 'Tomaat, mozzarella en basilicum · Ø 20 cm · dunne bodem'],
            ['Salades', 'Griekse salade', 'Tomaat, komkommer, feta, olijven, rode ui, olijfolie, oregano en zout', 1095, false, ['milk'], 'Tomaat, komkommer, feta, olijven en rode ui'],
            ['Koffie & dranken', 'Thee', 'Theebladeren en water', 275, false, null, 'Keuze uit verschillende soorten thee'],
            ['Dranken', 'Bruisend water', 'Bruisend mineraalwater', 275, false, null, null],
            ['Dranken', 'Cola', 'Frisdrank', 295, false, null, null],
            ['Dranken', 'Appelsap', 'Appelsap', 295, false, null, null],
            ['Dranken', 'Bier (fles)', 'Water, gerstemout, hop en gist', 425, true, ['gluten'], 'Bier'],
            ['Dranken', 'Rode wijn (glas)', 'Rode wijn', 495, true, null, null],
            ['Dranken', 'Witte wijn (glas)', 'Witte wijn', 495, true, null, null],
        ];

        $dietaryLabelsByName = [
            'Friet met mayonaise' => ['vegetarian'],
            'Groentesticks met hummus' => ['vegetarian', 'vegan'],
            'Espresso' => ['vegetarian', 'vegan'],
            'Thee' => ['vegetarian', 'vegan'],
            'Cappuccino' => ['vegetarian'],
            'Latte macchiato' => ['vegetarian'],
            'Bruisend water' => ['vegetarian', 'vegan', 'gluten_free'],
        ];
        $allergenFreeRecipes = ['Espresso', 'Thee', 'Bruisend water'];

        foreach ($definitions as $definition) {
            [$category, $name, $description, $priceCents, $alcoholic, $allergens, $oldDescription] = $definition;
            $dietaryLabels = $dietaryLabelsByName[$name] ?? null;
            $seedAllergens = in_array($name, $allergenFreeRecipes, true) ? [] : $allergens;
            if (isset($itemsByName[$name])) {
                if (1 !== count($itemsByName[$name])) {
                    continue;
                }
                foreach ($itemsByName[$name] as $item) {
                    if ($item->getCategory() !== $category || $item->getPriceCents() !== $priceCents || !$item->isActive()
                        || $item->isAlcoholic() !== $alcoholic || null !== $item->getDietaryLabels()) {
                        continue;
                    }
                    // Een oude, onvolledige beschrijving onderbouwt geen nieuwe dieetclaims.
                    if (null !== $oldDescription && $item->getDescription() === $oldDescription && null === $item->getAllergens()
                        && null === $dietaryLabels) {
                        $item->setDescription($description);
                        $item->setAllergens($seedAllergens);
                        $item->setDietaryLabels($dietaryLabels);
                    } elseif ($item->getDescription() === $description && $item->getAllergens() === $allergens) {
                        $item->setAllergens($seedAllergens);
                        $item->setDietaryLabels($dietaryLabels);
                    }
                }
                continue;
            }

            $item = new MenuItem($name, $priceCents);
            $item->setCategory($category);
            $item->setDescription($description);
            $item->setAlcoholic($alcoholic);
            $item->setAllergens($seedAllergens);
            $item->setDietaryLabels($dietaryLabels);
            $this->em->persist($item);
            $itemsByName[$name] = [$item];
        }

        $hasEspresso = array_any($itemsByName['Espresso'] ?? [], static fn (MenuItem $item): bool => $item->isActive());
        $hasTea = array_any($itemsByName['Thee'] ?? [], static fn (MenuItem $item): bool => $item->isActive());
        foreach ($itemsByName['Koffie / thee'] ?? [] as $item) {
            if ('Dranken' === $item->getCategory() && 'Klassiek' === $item->getDescription()
                && 320 === $item->getPriceCents() && $item->isActive() && !$item->isAlcoholic()
                && null === $item->getAllergens() && null === $item->getDietaryLabels() && $hasEspresso && $hasTea
                && 1 === count($itemsByName['Koffie / thee'])) {
                $item->setActive(false);
            }
        }
    }

    /**
     * @return array{email: string, password: string}|null
     */
    private function seedStaff(): ?array
    {
        if (0 !== $this->em->getRepository(User::class)->count()) {
            return null;
        }

        $email = 'personeel@restaurant.app';
        $password = bin2hex(random_bytes(8));

        $user = new User($email);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);

        return ['email' => $email, 'password' => $password];
    }
}
