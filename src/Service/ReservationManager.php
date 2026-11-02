<?php

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\Table;
use App\Repository\ReservationRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Centraliseert reserveringsregels en serialiseert wijzigingen per tafel.
 *
 * De pure regels (capacityError) zijn testbaar zonder database.
 */
final class ReservationManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReservationRepository $reservations,
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * Retourneert een lijst met tekstuele redenen waarom de
     * reservering niet aangenomen kan worden. Een lege lijst
     * betekent: de reservering is geldig.
     *
     * @return list<string>
     */
    public function findBlockingReasons(Reservation $reservation): array
    {
        $reasons = [];

        $capacityError = self::capacityError($reservation, $this->translator);
        if (null !== $capacityError) {
            $reasons[] = $capacityError;
        }

        $table = $reservation->getTable();
        $date = $reservation->getDate();
        $time = $reservation->getTime();
        $durationMinutes = $reservation->getDurationMinutes();

        if ($durationMinutes < 15 || $durationMinutes > 1440) {
            $reasons[] = self::message('errors.duration', 'De reserveringsduur moet tussen 15 minuten en 24 uur liggen.', [], $this->translator);
        }

        if (
            $table instanceof Table
            && !$table->isActive()
        ) {
            $reasons[] = self::message('errors.unavailable', 'Deze tafel is niet beschikbaar.', [], $this->translator);
        }

        if (
            $table instanceof Table
            && $date instanceof \DateTimeImmutable
            && $time instanceof \DateTimeImmutable
            && $durationMinutes >= 15
            && $durationMinutes <= 1440
        ) {
            $startsAt = self::combineDateAndTime($date, $time);
            if ($startsAt < new \DateTimeImmutable('now', $startsAt->getTimezone())) {
                $reasons[] = self::message('errors.past', 'Datum en tijd mogen niet in het verleden liggen.', [], $this->translator);
            }

            $endsAt = $startsAt->add(new \DateInterval(sprintf('PT%dM', $durationMinutes)));
            $from = $startsAt->setTime(0, 0)->modify('-1 day');
            $to = $endsAt->setTime(0, 0);

            foreach ($this->reservations->findActiveForTableBetweenDates($table, $from, $to) as $existing) {
                if (
                    (null !== $reservation->getId() && $existing->getId() === $reservation->getId())
                    || $existing->isCancelled()
                    || !$existing->getDate() instanceof \DateTimeImmutable
                    || !$existing->getTime() instanceof \DateTimeImmutable
                ) {
                    continue;
                }

                if (self::intervalsOverlap(
                    $startsAt,
                    $durationMinutes,
                    self::combineDateAndTime($existing->getDate(), $existing->getTime()),
                    $existing->getDurationMinutes(),
                )) {
                    $reasons[] = self::message('errors.overlap', 'Deze tafel is rond %time% al gereserveerd.', ['%time%' => $existing->getTime()->format('H:i')], $this->translator);
                    break;
                }
            }
        }

        return $reasons;
    }

    public static function intervalsOverlap(
        \DateTimeImmutable $startsAt,
        int $durationMinutes,
        \DateTimeImmutable $otherStartsAt,
        int $otherDurationMinutes,
    ): bool {
        if ($durationMinutes < 1 || $otherDurationMinutes < 1) {
            throw new \InvalidArgumentException('Reserveringsintervallen moeten een positieve duur hebben.');
        }

        $endsAt = $startsAt->add(new \DateInterval(sprintf('PT%dM', $durationMinutes)));
        $otherEndsAt = $otherStartsAt->add(new \DateInterval(sprintf('PT%dM', $otherDurationMinutes)));

        return $startsAt < $otherEndsAt && $otherStartsAt < $endsAt;
    }

    /**
     * De capacity-regel als pure functie, dus unit-testbaar
     * zonder database of entitymanager.
     */
    public static function capacityError(Reservation $reservation, ?TranslatorInterface $translator = null): ?string
    {
        $table = $reservation->getTable();
        if (null === $table) {
            return self::message('errors.choose_table', 'Kies een tafel.', [], $translator);
        }

        $guests = $reservation->getGuests();
        if (null === $guests) {
            return self::message('errors.guests_required', 'Geef het aantal gasten op.', [], $translator);
        }

        if ($guests < 1 || $guests > $table->getCapacity()) {
            return self::message('errors.capacity', 'Het aantal gasten (%guests%) moet tussen 1 en %capacity% liggen voor deze tafel.', ['%guests%' => (string) $guests, '%capacity%' => (string) $table->getCapacity()], $translator);
        }

        return null;
    }

    public function create(Reservation $reservation): void
    {
        $table = $reservation->getTable();
        if (!$table instanceof Table) {
            throw new \DomainException(self::message('errors.choose_before_save', 'Kies een tafel voordat de reservering wordt opgeslagen.', [], $this->translator));
        }

        $this->withLockedTable($table, function () use ($reservation): void {
            $reasons = $this->findBlockingReasons($reservation);
            if ([] !== $reasons) {
                throw new \DomainException(implode(' ', $reasons));
            }

            $reservation->setStatus(Reservation::STATUS_CONFIRMED);
            $this->entityManager->persist($reservation);
        });
    }

    public function setStatus(Reservation $reservation, string $status): void
    {
        if (!in_array($status, [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED, Reservation::STATUS_CANCELLED], true)) {
            throw new \InvalidArgumentException('Onbekende reserveringsstatus.');
        }

        $table = $reservation->getTable();
        if (!$table instanceof Table) {
            throw new \DomainException(self::message('errors.no_table', 'Bij deze reservering hoort geen tafel.', [], $this->translator));
        }

        $this->withLockedTable($table, function () use ($reservation, $status): void {
            if (Reservation::STATUS_CANCELLED !== $status) {
                $reasons = $this->findBlockingReasons($reservation);
                if ([] !== $reasons) {
                    throw new \DomainException(implode(' ', $reasons));
                }
            }

            $reservation->setStatus($status);
        });
    }

    /** @param array<string, string> $parameters */
    private static function message(string $key, string $fallback, array $parameters, ?TranslatorInterface $translator): string
    {
        return null === $translator ? strtr($fallback, $parameters) : $translator->trans($key, $parameters);
    }

    private function withLockedTable(Table $table, callable $operation): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $this->entityManager->lock($table, LockMode::PESSIMISTIC_WRITE);
            $operation();
            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    private static function combineDateAndTime(\DateTimeImmutable $date, \DateTimeImmutable $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable(
            $date->format('Y-m-d').' '.$time->format('H:i:s'),
            $date->getTimezone(),
        );
    }
}
