<?php

namespace App\Controller;

use App\Repository\MenuItemRepository;
use App\Repository\TableRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Publieke startsite: hero, "over ons", een paar gerechten uit het menu,
 * openingstijden en een reserverings-uitnodiging.
 */
class HomeController extends AbstractController
{
    public function __construct(
        private readonly MenuItemRepository $menuItems,
        private readonly TableRepository $tables,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        // Een korte "signatuur"-keuze uit het actieve menu voor de teaser op de home.
        $featured = array_slice($this->menuItems->findByActive(), 0, 6);
        $tablesByName = [];
        foreach ($this->tables->findByActive() as $table) {
            $tablesByName[$table->getName()][] = $table;
        }

        $layout = [
            'Tafel 1' => [1, 180, 285, 'inside'],
            'Tafel 2' => [2, 460, 285, 'inside'],
            'Tafel 3' => [3, 180, 465, 'inside'],
            'Tafel 4' => [4, 460, 465, 'inside'],
            'Tafel 5' => [5, 820, 275, 'outside'],
            'Tafel 6' => [6, 820, 465, 'outside'],
        ];
        $floorPlan = [];
        foreach ($layout as $name => [$number, $x, $y, $area]) {
            $matches = $tablesByName[$name] ?? [];
            if (1 === count($matches) && $matches[0]->getCapacity() > 0) {
                $floorPlan[] = ['number' => $number, 'x' => $x, 'y' => $y, 'area' => $area, 'capacity' => $matches[0]->getCapacity()];
            }
        }
        $activeCount = array_sum(array_map('count', $tablesByName));

        return $this->render('home/index.html.twig', [
            'featured' => $featured,
            'floorPlan' => $floorPlan,
            'otherTables' => $activeCount > count($floorPlan),
        ]);
    }
}
