<?php

namespace App\Tests\Controller;

use App\Entity\MenuItem;
use App\Entity\User;
use App\Twig\RestaurantExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use Twig\Environment;

/**
 * Browser tests for public reservation and staff-access behavior.
 * Homepage rendering also depends on the configured menu database.
 */
final class HomeControllerTest extends IsolatedDatabaseWebTestCase
{
    /** @return iterable<array{string, string}> */
    public static function routes(): iterable
    {
        yield ['', '/nl'];
        yield ['/nl', '/nl'];
        yield ['/en', '/en'];
        yield ['/de', '/de'];
        yield ['/fr', '/fr'];
        yield ['/it', '/it'];
    }

    #[DataProvider('routes')]
    public function testHomepageRendersSuccessfully(string $prefix, string $localePrefix): void
    {
        $client = $this->clientWithDatabase();
        $crawler = $client->request('GET', $prefix.'/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('De Gouden Lepel', $crawler->text());
        $this->assertStringContainsString('Schiphol', $crawler->text());
        self::assertGreaterThan(0, $crawler->filterXPath('//a[@href="'.$localePrefix.'/reserveren"]')->count());
    }

    public function testHomepageAlcoholAndPriceFollowTheSelectedLanguage(): void
    {
        $client = $this->clientWithDatabase();
        $wine = new MenuItem('Huiswijn (glas)', 480);
        $wine->setCategory('Dranken');
        $this->entityManager()->persist($wine);
        $this->entityManager()->flush();

        foreach (['nl' => '4,80', 'en' => '4.80'] as $locale => $price) {
            $crawler = $client->request('GET', '/'.$locale.'/');
            self::assertResponseIsSuccessful();
            $item = $crawler->filterXPath('//article[contains(@class, "card")]')->first()->text();
            self::assertStringContainsString('18+', $item);
            self::assertStringContainsString($price, $item);
        }
    }

    #[DataProvider('routes')]
    public function testReservationPageOffersTelephoneBookingWithoutPublicForm(string $prefix, string $localePrefix): void
    {
        $client = $this->clientWithDatabase();
        $crawler = $client->request('GET', $prefix.'/reserveren');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('+31 20 123456', $crawler->text());
        $this->assertStringContainsString('Schiphol', $crawler->text());
        self::assertGreaterThan(0, $crawler->filterXPath('//a[@href="tel:+3120123456"]')->count());
        self::assertSame(0, $crawler->filterXPath('//form')->count());
        self::assertGreaterThan(0, $crawler->filterXPath('//a[@href="'.$localePrefix.'/menu"]')->count());
    }

    public function testReservationStepsRemainVisibleWithoutPhone(): void
    {
        $client = $this->clientWithDatabase();
        $twig = self::getContainer()->get(Environment::class);
        self::assertInstanceOf(Environment::class, $twig);
        $restaurant = RestaurantExtension::info();
        $restaurant['phone'] = null;
        $twig->addGlobal('restaurant', $restaurant);

        $crawler = $client->request('GET', '/nl/reserveren');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Het telefoonnummer is momenteel niet beschikbaar.', $crawler->text());
        self::assertCount(0, $crawler->filterXPath('//a[starts-with(@href, "tel:")]'));
        self::assertCount(3, $crawler->filterXPath('//ol[@class="call-steps"]/li'));
    }

    public function testConfiguredContactDetailsRemainVisible(): void
    {
        $client = $this->clientWithDatabase();
        $twig = self::getContainer()->get(Environment::class);
        self::assertInstanceOf(Environment::class, $twig);
        $restaurant = RestaurantExtension::info();
        $restaurant['phone'] = '+31 (20) 987-6543';
        $restaurant['email'] = 'contact@example.test';
        $twig->addGlobal('restaurant', $restaurant);

        foreach (['/' => 3, '/reserveren' => 2, '/login' => 1] as $path => $phoneLinks) {
            $crawler = $client->request('GET', '/nl'.$path);
            self::assertResponseIsSuccessful();
            self::assertCount($phoneLinks, $crawler->filterXPath('//a[@href="tel:+31209876543"]'));
            self::assertStringContainsString('+31 (20) 987-6543', $crawler->text());
            self::assertGreaterThan(0, $crawler->filterXPath('//a[@href="mailto:contact@example.test"]')->count());
            self::assertStringNotContainsString('Het telefoonnummer is momenteel niet beschikbaar.', $crawler->text());
            self::assertCount(0, $crawler->filterXPath('//*[contains(concat(" ", normalize-space(@class), " "), " demo-note ")]'));
            if ('/reserveren' === $path) {
                self::assertCount(3, $crawler->filterXPath('//ol[@class="call-steps"]/li'));
            }
        }
    }

    #[DataProvider('routes')]
    public function testPublicReservationRouteDoesNotAcceptSubmissions(string $prefix, string $localePrefix): void
    {
        $client = $this->clientWithDatabase();
        $client->request('POST', $prefix.'/reserveren');

        $this->assertResponseStatusCodeSame(405);
    }

    #[DataProvider('routes')]
    public function testStaffDashboardRequiresLogin(string $prefix, string $localePrefix): void
    {
        $client = $this->clientWithDatabase();
        $client->request('GET', $prefix.'/dashboard');

        $this->assertResponseRedirects($localePrefix.'/login');
    }

    #[DataProvider('routes')]
    public function testStaffReservationCreationRequiresLogin(string $prefix, string $localePrefix): void
    {
        $client = $this->clientWithDatabase();
        $client->request('GET', $prefix.'/dashboard/reserveren');

        $this->assertResponseRedirects($localePrefix.'/login');
    }

    #[DataProvider('routes')]
    public function testReservationDetailsRequireStaffLogin(string $prefix, string $localePrefix): void
    {
        $client = $this->clientWithDatabase();
        $client->request('GET', $prefix.'/dashboard/reserveren/42');

        $this->assertResponseRedirects($localePrefix.'/login');
    }

    #[DataProvider('routes')]
    public function testStatusChangesRequireCsrfToken(string $prefix, string $localePrefix): void
    {
        $client = $this->clientWithDatabase();
        $user = new User('staff@example.test');
        $user->setPassword('unused');
        $entityManager = $this->entityManager();
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);
        $client->request('POST', $prefix.'/dashboard/reserveren/42/status', ['status' => 'confirmed']);

        $this->assertResponseStatusCodeSame(403);
    }

}
