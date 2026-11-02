<?php

namespace App\Entity;

use App\Repository\MenuItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuItemRepository::class)]
#[ORM\Table(name: 'menu_item')]
class MenuItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 60, nullable: true, name: 'category')]
    private ?string $category = null;

    /** Prijs opgeslagen in cent zodat er geen float-precision-fouten ontstaan. */
    #[ORM\Column(type: 'integer', name: 'price_cents')]
    private int $priceCents = 0;

    #[ORM\Column(type: 'boolean', name: 'is_active')]
    private bool $active = true;

    /** @var list<string>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $allergens = null;

    /** @var list<string>|null */
    #[ORM\Column(name: 'dietary_labels', type: 'json', nullable: true)]
    private ?array $dietaryLabels = null;

    #[ORM\Column(type: 'boolean', name: 'is_alcoholic', options: ['default' => false])]
    private bool $alcoholic = false;

    public function __construct(string $name, int $priceCents)
    {
        $this->name = $name;
        $this->priceCents = $priceCents;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): void
    {
        $this->category = $category;
    }

    public function getPriceCents(): int
    {
        return $this->priceCents;
    }

    public function setPriceCents(int $priceCents): void
    {
        $this->priceCents = $priceCents;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    /** @return list<string>|null */
    public function getAllergens(): ?array
    {
        return $this->allergens;
    }

    /** @param list<string>|null $allergens */
    public function setAllergens(?array $allergens): void
    {
        $this->allergens = $allergens;
    }

    /** @return list<string>|null */
    public function getDietaryLabels(): ?array
    {
        return $this->dietaryLabels;
    }

    /** @param list<string>|null $dietaryLabels */
    public function setDietaryLabels(?array $dietaryLabels): void
    {
        $this->dietaryLabels = $dietaryLabels;
    }

    public function isAlcoholic(): bool
    {
        return $this->alcoholic;
    }

    public function setAlcoholic(bool $alcoholic): void
    {
        $this->alcoholic = $alcoholic;
    }
}
