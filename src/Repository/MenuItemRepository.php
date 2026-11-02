<?php

namespace App\Repository;

use App\Entity\MenuItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuItem>
 */
class MenuItemRepository extends ServiceEntityRepository
{
    /**
     * @var string[]
     */
    private const SORT_ORDER = ['category' => 'ASC', 'name' => 'ASC'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuItem::class);
    }

    /**
     * @return array<int, MenuItem>
     */
    public function findByActive(): array
    {
        return $this->findBy(
            ['active' => true],
            self::SORT_ORDER,
        );
    }
}
