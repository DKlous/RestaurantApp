<?php

namespace App\Repository;

use App\Entity\Reservation;
use App\Entity\Table;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * @return list<Reservation>
     */
    public function findActiveForTableBetweenDates(
        Table $table,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array
    {
        /** @var list<Reservation> $reservations */
        $reservations = $this->createQueryBuilder('reservation')
            ->andWhere('reservation.table = :table')
            ->andWhere('reservation.date BETWEEN :from AND :to')
            ->andWhere('reservation.status IN (:statuses)')
            ->setParameter('table', $table)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('statuses', [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED])
            ->orderBy('reservation.date', 'ASC')
            ->addOrderBy('reservation.time', 'ASC')
            ->getQuery()
            ->getResult();

        return $reservations;
    }

    /**
     * @return array<int, Reservation>
     */
    public function findUpcoming(): array
    {
        return $this->findBy([], [
            'date' => 'ASC',
            'time' => 'ASC',
            'id' => 'ASC',
        ]);
    }
}
