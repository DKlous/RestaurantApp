<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Reservation;
use App\Entity\Table;
use App\Repository\ReservationRepository;
use App\Service\ReservationManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ReservationManagerTest extends TestCase
{
    public function testDetectsOverlappingIntervalsOnTheSameDay(): void
    {
        $this->assertTrue(ReservationManager::intervalsOverlap(
            new \DateTimeImmutable('2026-11-03 18:00'),
            90,
            new \DateTimeImmutable('2026-11-03 19:00'),
            60,
        ));
    }

    public function testAllowsAdjacentIntervals(): void
    {
        $this->assertFalse(ReservationManager::intervalsOverlap(
            new \DateTimeImmutable('2026-11-03 18:00'),
            90,
            new \DateTimeImmutable('2026-11-03 19:30'),
            60,
        ));
    }

    public function testDetectsOverlapAcrossMidnight(): void
    {
        $this->assertTrue(ReservationManager::intervalsOverlap(
            new \DateTimeImmutable('2026-11-03 23:30'),
            90,
            new \DateTimeImmutable('2026-11-04 00:30'),
            60,
        ));
    }

    public function testCancelledReservationDoesNotBlockSlotReuse(): void
    {
        $table = new Table('Tafel 1', 2);
        $requested = $this->reservation($table, '2030-11-03 19:00');
        $cancelled = $this->reservation($table, '2030-11-03 19:00');
        $cancelled->setStatus(Reservation::STATUS_CANCELLED);

        $repository = $this->createMock(ReservationRepository::class);
        $repository->method('findActiveForTableBetweenDates')->willReturn([$cancelled]);
        $manager = new ReservationManager($this->createMock(EntityManagerInterface::class), $repository);

        self::assertSame([], $manager->findBlockingReasons($requested));
    }

    public function testRejectsAnOverlappingActiveReservation(): void
    {
        $table = new Table('Tafel 1', 2);
        $requested = $this->reservation($table, '2030-11-03 19:00');
        $existing = $this->reservation($table, '2030-11-03 19:30');

        $repository = $this->createMock(ReservationRepository::class);
        $repository->method('findActiveForTableBetweenDates')->willReturn([$existing]);
        $manager = new ReservationManager($this->createMock(EntityManagerInterface::class), $repository);

        self::assertNotSame([], $manager->findBlockingReasons($requested));
    }

    public function testReactivationCannotClaimAnOccupiedSlot(): void
    {
        $table = new Table('Tafel 1', 2);
        $cancelled = $this->reservation($table, '2030-11-03 19:00');
        $cancelled->setStatus(Reservation::STATUS_CANCELLED);
        $occupying = $this->reservation($table, '2030-11-03 19:30');

        $repository = $this->createMock(ReservationRepository::class);
        $repository->method('findActiveForTableBetweenDates')->willReturn([$occupying]);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('rollBack');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->expects(self::once())->method('lock')->with($table, LockMode::PESSIMISTIC_WRITE);

        $manager = new ReservationManager($entityManager, $repository);

        try {
            $manager->setStatus($cancelled, Reservation::STATUS_CONFIRMED);
            self::fail('Reactivation should be rejected when another active reservation overlaps.');
        } catch (\DomainException) {
            self::assertSame(Reservation::STATUS_CANCELLED, $cancelled->getStatus());
        }
    }

    public function testGuestCountCannotExceedTableCapacity(): void
    {
        $reservation = $this->reservation(new Table('Tafel 1', 2), '2030-11-03 19:00');
        $reservation->setGuests(3);

        self::assertNotNull(ReservationManager::capacityError($reservation));
    }

    public function testFindBlockingReasonsDetectsAnOverlapFromThePreviousNight(): void
    {
        $table = new Table('Tafel 1', 2);
        $requested = $this->reservation($table, '2030-11-04 00:30');
        $existing = $this->reservation($table, '2030-11-03 23:30');

        $repository = $this->createMock(ReservationRepository::class);
        $repository->method('findActiveForTableBetweenDates')->willReturn([$existing]);
        $manager = new ReservationManager($this->createMock(EntityManagerInterface::class), $repository);

        self::assertNotSame([], $manager->findBlockingReasons($requested));
    }

    public function testCreateLocksTheTableAndConfirmsTheReservation(): void
    {
        $table = new Table('Tafel 1', 2);
        $reservation = $this->reservation($table, '2030-11-03 19:00');
        $repository = $this->createMock(ReservationRepository::class);
        $repository->method('findActiveForTableBetweenDates')->willReturn([]);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->expects(self::once())->method('lock')->with($table, LockMode::PESSIMISTIC_WRITE);
        $entityManager->expects(self::once())->method('persist')->with($reservation);
        $entityManager->expects(self::once())->method('flush');

        $manager = new ReservationManager($entityManager, $repository);
        $manager->create($reservation);

        self::assertSame(Reservation::STATUS_CONFIRMED, $reservation->getStatus());
    }

    private function reservation(Table $table, string $startsAt, int $durationMinutes = 90): Reservation
    {
        $start = new \DateTimeImmutable($startsAt);
        $reservation = new Reservation();
        $reservation->setTable($table);
        $reservation->setDate($start->setTime(0, 0));
        $reservation->setTime($start);
        $reservation->setGuests(2);
        $reservation->setDurationMinutes($durationMinutes);

        return $reservation;
    }
}
