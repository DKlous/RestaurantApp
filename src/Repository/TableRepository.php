<?php

namespace App\Repository;

use App\Entity\Table;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Table>
 */
class TableRepository extends ServiceEntityRepository
{
    /**
     * @var string[]
     */
    private const SORT_ORDER = ['name' => 'ASC'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Table::class);
    }

    /**
     * @return array<int, Table>
     */
    public function findByActive(): array
    {
        return $this->findBy(
            ['active' => true],
            self::SORT_ORDER,
        );
    }
}
