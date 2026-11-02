<?php

namespace App\Controller;

use App\Entity\MenuItem;
use App\Repository\MenuItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MenuController extends AbstractController
{
    #[Route(path: '/menu', name: 'menu', methods: ['GET'])]
    public function index(MenuItemRepository $menuItems): Response
    {
        $items = $menuItems->findByActive();
        $groupedItems = [];
        foreach ($items as $item) {
            $category = $item->getCategory() ?? '';
            $groupedItems['Koffie & dranken' === $category ? 'Dranken' : $category][] = $item;
        }

        if (isset($groupedItems['Dranken'])) {
            $order = array_flip(['Cappuccino', 'Latte macchiato', 'Espresso', 'Thee', 'Bruisend water']);
            $rank = static fn (MenuItem $item): int => $item->isAlcoholic() || 'Huiswijn (glas)' === $item->getName()
                ? 6
                : ($order[$item->getName()] ?? 5);
            usort($groupedItems['Dranken'], static fn (MenuItem $a, MenuItem $b): int => ($rank($a) <=> $rank($b)) ?: strcmp($a->getName(), $b->getName()));
        }

        return $this->render('menu/index.html.twig', [
            'items' => $items,
            'groupedItems' => $groupedItems,
        ]);
    }
}
