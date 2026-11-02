<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Levert de merk-/contactgegevens van het restaurant als een enkele Twig-global
 * ("restaurant") op. Zo is de bedrijfsnaam, adressen, openingstijden en de
 * "over ons"-tekst op één plek te onderhouden en wordt die hergebruikt door de
 * header, footer, de home-pagina en de reserveringspagina's.
 */
final class RestaurantExtension extends AbstractExtension implements GlobalsInterface
{
    public function getGlobals(): array
    {
        return ['restaurant' => self::info()];
    }

    /**
     * De "marketing"-gegevens van het restaurant. Bewaarbaar als één bron;
     * aan te passen in de code of te vervangen door een instellingen-domein.
     *
     * @return array<string, mixed>
     */
    public static function info(): array
    {
        return [
            'name' => 'De Gouden Lepel',
            'short' => 'restaurant.short',
            'tagline' => 'restaurant.tagline',
            'contactNote' => 'restaurant.contact_note',
            'address' => [
                'street'   => 'Schiphol Airport',
                'city'     => '',
                'country'  => '',
            ],
            'phone'   => '+31 20 123456',
            'email'   => null,
            'about' => 'restaurant.about',
            'hours' => [
                ['day' => 'days.monday', 'time' => 'restaurant.open_24', 'open' => true],
                ['day' => 'days.tuesday', 'time' => 'restaurant.open_24', 'open' => true],
                ['day' => 'days.wednesday', 'time' => 'restaurant.open_24', 'open' => true],
                ['day' => 'days.thursday', 'time' => 'restaurant.open_24', 'open' => true],
                ['day' => 'days.friday', 'time' => 'restaurant.open_24', 'open' => true],
                ['day' => 'days.saturday', 'time' => 'restaurant.open_24', 'open' => true],
                ['day' => 'days.sunday', 'time' => 'restaurant.open_24', 'open' => true],
            ],
        ];
    }
}
