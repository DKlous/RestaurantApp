<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservation')]
#[ORM\Index(name: 'idx_reservation_table_date', columns: ['table_id', 'date'])]
#[ORM\HasLifecycleCallbacks]
class Reservation
{
    public const string STATUS_PENDING = 'pending';
    public const string STATUS_CONFIRMED = 'confirmed';
    public const string STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Table::class)]
    #[ORM\JoinColumn(name: 'table_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Table $table = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(type: 'time_immutable')]
    private ?\DateTimeImmutable $time = null;

    #[ORM\Column(type: 'integer', name: 'duration_minutes', options: ['default' => 90])]
    private int $durationMinutes = 90;

    #[ORM\Column(type: 'integer')]
    private ?int $guests = null;

    #[ORM\Column(type: 'string', length: 20, name: 'status', options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at', nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTable(): ?Table
    {
        return $this->table;
    }

    public function setTable(Table $table): void
    {
        $this->table = $table;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): void
    {
        $this->date = $date;
    }

    public function getTime(): ?\DateTimeImmutable
    {
        return $this->time;
    }

    public function setTime(\DateTimeImmutable $time): void
    {
        $this->time = $time;
    }

    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(int $durationMinutes): void
    {
        $this->durationMinutes = $durationMinutes;
    }

    public function getGuests(): ?int
    {
        return $this->guests;
    }

    public function setGuests(int $guests): void
    {
        $this->guests = $guests;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        if (!in_array($status, [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_CANCELLED], true)) {
            throw new \InvalidArgumentException('Onbekende reserveringsstatus.');
        }

        $this->status = $status;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isConfirmed(): bool
    {
        return self::STATUS_CONFIRMED === $this->status;
    }

    public function isCancelled(): bool
    {
        return self::STATUS_CANCELLED === $this->status;
    }

    #[ORM\PrePersist]
    public function setCurrentTimestamp(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
