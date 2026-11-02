<?php

namespace App\Tests\Controller;

use App\Entity\Reservation;
use App\Entity\Table;
use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class LocalizationTest extends IsolatedDatabaseWebTestCase
{
    /** @return iterable<array{string, string, string, string, string, list<string>}> */
    public static function languages(): iterable
    {
        yield ['nl', 'Reserveren', 'demonstratienummer is niet geverifieerd', 'Bel +31 20 123456', 'In drie stappen geregeld', ['Bel ons', 'Stem de tafel af', 'Wij noteren je reservering']];
        yield ['en', 'Reservations', 'demonstration number is not verified', 'Call +31 20 123456', 'Three simple steps', ['Call us', 'Choose a table', 'We record your reservation']];
        yield ['de', 'Reservieren', 'Demonstrationsnummer ist nicht verifiziert', '+31 20 123456 anrufen', 'In drei Schritten erledigt', ['Rufen Sie uns an', 'Tisch abstimmen', 'Wir notieren Ihre Reservierung']];
        yield ['fr', 'Réserver', 'numéro de démonstration n’est pas vérifié', 'Appeler le +31 20 123456', 'En trois étapes', ['Appelez-nous', 'Choisissez une table', 'Nous notons votre réservation']];
        yield ['it', 'Prenotare', 'numero dimostrativo non è verificato', 'Chiama +31 20 123456', 'Tre semplici passi', ['Chiamaci', 'Scegli il tavolo', 'Annotiamo la prenotazione']];
    }

    /** @param list<string> $steps */
    #[DataProvider('languages')]
    public function testLanguageAndNavigation(string $locale, string $label, string $warning, string $phoneLabel, string $stepsHeading, array $steps): void
    {
        $client = $this->clientWithDatabase();
        $crawler = $client->request('GET', '/'.$locale.'/');

        self::assertResponseIsSuccessful();
        self::assertSame($locale, $crawler->filterXPath('//html')->attr('lang'));
        self::assertStringContainsString($label, $crawler->filterXPath('//nav')->first()->text());
        self::assertGreaterThan(0, $crawler->filterXPath('//a[@href="/'.$locale.'/menu"]')->count());
        self::assertGreaterThan(0, $crawler->filterXPath('//a[@href="/fr/"]')->count());
        self::assertStringContainsString('Schiphol', $crawler->text());

        foreach (['/', '/reserveren', '/login'] as $path) {
            $crawler = $client->request('GET', '/'.$locale.$path);
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('+31 20 123456', $crawler->text());
            self::assertStringNotContainsString($warning, $crawler->text());
            self::assertGreaterThan(0, $crawler->filterXPath('//a[@href="tel:+3120123456"]')->count());
            self::assertCount(0, $crawler->filterXPath('//*[contains(concat(" ", normalize-space(@class), " "), " demo-note ")]'));
            if ('/reserveren' === $path) {
                self::assertStringContainsString($phoneLabel, $crawler->text());
                self::assertStringContainsString($stepsHeading, $crawler->text());
                self::assertCount(3, $crawler->filterXPath('//ol[@class="call-steps"]/li'));
                foreach ($steps as $step) {
                    self::assertStringContainsString($step, $crawler->text());
                }
            }
        }
    }

    public function testLanguageSwitchKeepsRouteParametersAndQuery(): void
    {
        $client = $this->clientWithDatabase();
        $entityManager = $this->entityManager();
        $user = $this->createUser();
        $table = new Table('Table 1', 4);
        $reservation = new Reservation();
        $reservation->setTable($table);
        $reservation->setDate(new \DateTimeImmutable('2030-01-01'));
        $reservation->setTime(new \DateTimeImmutable('2030-01-01 18:00'));
        $reservation->setGuests(2);
        $entityManager->persist($table);
        $entityManager->persist($reservation);
        $entityManager->flush();
        $client->loginUser($user);
        $crawler = $client->request('GET', '/en/dashboard/reserveren/'.$reservation->getId().'?from=overview');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filterXPath('//a[@href="/fr/dashboard/reserveren/'.$reservation->getId().'?from=overview"]')->count());
        $crawler = $client->request('GET', '/en/reserveren?from=overview');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filterXPath('//a[@href="/fr/reserveren?from=overview"]')->count());
        $crawler = $client->request('GET', '/en/menu?from=overview');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filterXPath('//a[@href="/fr/menu?from=overview"]')->count());
    }

    public function testSecurityKeepsLocaleAndRejectsBadCredentials(): void
    {
        $client = $this->clientWithDatabase();
        $client->request('GET', '/fr/dashboard');
        self::assertResponseRedirects('/fr/login');
        $crawler = $client->followRedirect();
        $client->submit($crawler->filterXPath('//form')->form(), ['_username' => 'unknown@example.test', '_password' => 'wrong']);
        self::assertResponseRedirects('/fr/login');
        $crawler = $client->followRedirect();
        self::assertStringContainsString('Identifiants invalides', $crawler->text());
    }

    public function testLoginAndLogoutKeepLocale(): void
    {
        $client = $this->clientWithDatabase();
        $this->createUser();

        $crawler = $client->request('GET', '/de/login');
        $client->submit($crawler->filterXPath('//form')->form(), ['_username' => 'staff@example.test', '_password' => 'password']);
        self::assertResponseRedirects('/de/');
        $client->request('GET', '/de/logout');
        self::assertResponseRedirects('/de/');
        $client->request('GET', '/de/dashboard');
        self::assertResponseRedirects('/de/login');
    }

    public function testLoginReturnsToProtectedPageInSelectedLocale(): void
    {
        $client = $this->clientWithDatabase();
        $this->createUser();
        $client->request('GET', '/it/dashboard/reserveren?from=overview');
        self::assertResponseRedirects('/it/login');
        $crawler = $client->followRedirect();
        self::assertGreaterThan(0, $crawler->filterXPath('//a[@href="/fr/login"]')->count());
        $client->submit($crawler->filterXPath('//form')->form(), ['_username' => 'staff@example.test', '_password' => 'password']);
        self::assertResponseRedirects('http://localhost/it/dashboard/reserveren?from=overview');
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filterXPath('//a[@href="/fr/dashboard/reserveren?from=overview"]')->count());
    }

    public function testLegacyLoginPostAndLogout(): void
    {
        $client = $this->clientWithDatabase();
        $this->createUser();
        $crawler = $client->request('GET', '/nl/login');
        $token = $crawler->filterXPath('//input[@name="_csrf_token"]')->attr('value');

        $client->request('POST', '/login', ['_username' => 'staff@example.test', '_password' => 'password', '_csrf_token' => $token]);
        self::assertResponseRedirects('/nl/login', 307);
        $client->followRedirect();
        self::assertResponseRedirects('/nl/');
        $client->request('GET', '/nl/dashboard');
        self::assertResponseIsSuccessful();
        $client->request('GET', '/logout');
        self::assertResponseRedirects('/nl/logout', 307);
        $client->followRedirect();
        self::assertResponseRedirects('/nl/');
        $client->request('GET', '/dashboard');
        self::assertResponseRedirects('/nl/login');
    }

    public function testLegacyLoginPostRejectsBadCredentialsAndCsrf(): void
    {
        $client = $this->clientWithDatabase();
        $this->createUser();
        $crawler = $client->request('GET', '/nl/login');
        $token = $crawler->filterXPath('//input[@name="_csrf_token"]')->attr('value');
        $client->request('POST', '/login', ['_username' => 'staff@example.test', '_password' => 'wrong', '_csrf_token' => $token]);
        self::assertResponseRedirects('/nl/login', 307);
        $client->followRedirect();
        self::assertResponseRedirects('/nl/login');
        $crawler = $client->followRedirect();
        self::assertStringContainsString('Ongeldige inloggegevens', $crawler->text());

        $client->request('POST', '/login', ['_username' => 'staff@example.test', '_password' => 'password', '_csrf_token' => 'invalid']);
        self::assertResponseRedirects('/nl/login', 307);
        $client->followRedirect();
        self::assertResponseRedirects('/nl/login');
        $crawler = $client->followRedirect();
        self::assertStringContainsString('CSRF-code is ongeldig', $crawler->text());
        $client->request('GET', '/nl/dashboard');
        self::assertResponseRedirects('/nl/login');
    }

    public function testEmptyReservationFormIsRejectedInSelectedLanguage(): void
    {
        $client = $this->clientWithDatabase();
        $client->loginUser($this->createUser());
        $crawler = $client->request('GET', '/it/dashboard/reserveren');
        $form = $crawler->filterXPath('//form')->form();
        $form['reservation_form[durationMinutes]'] = '';
        $crawler = $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertGreaterThan(0, $crawler->filterXPath('//*[@role="alert"]')->count());
        self::assertSame(5, substr_count($crawler->filterXPath('//*[@role="alert"]')->text(), 'Questo valore non dovrebbe essere vuoto.'));
    }

    public function testStatusCsrfFailureUsesSelectedLanguage(): void
    {
        $client = $this->clientWithDatabase();
        $client->loginUser($this->createUser());
        $client->catchExceptions(false);
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Jeton CSRF invalide.');
        $client->request('POST', '/fr/dashboard/reserveren/42/status', ['status' => 'confirmed']);
    }

    public function testReservationValidationAndStatusMessages(): void
    {
        $client = $this->clientWithDatabase();
        $client->loginUser($this->createUser());
        $table = new Table('Table 1', 4);
        $this->entityManager()->persist($table);
        $this->entityManager()->flush();

        $crawler = $client->request('GET', '/fr/dashboard/reserveren');
        self::assertResponseIsSuccessful();
        $form = $crawler->filterXPath('//form')->form();
        $form['reservation_form[table]'] = '0';
        $form['reservation_form[date]'] = (new \DateTimeImmutable('+2 years'))->format('Y-m-d');
        $form['reservation_form[time]'] = '18:00';
        $form['reservation_form[guests]'] = '2';
        $form['reservation_form[durationMinutes]'] = '10';
        $crawler = $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('15', $crawler->filterXPath('//*[@role="alert"]')->text());
        self::assertStringContainsString('Cette valeur', $crawler->filterXPath('//*[@role="alert"]')->text());

        $form = $crawler->filterXPath('//form')->form();
        $form['reservation_form[guests]'] = '5';
        $form['reservation_form[durationMinutes]'] = '90';
        $crawler = $client->submit($form);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('5', $crawler->filterXPath('//*[@role="alert"]')->text());
        self::assertStringContainsString('4', $crawler->filterXPath('//*[@role="alert"]')->text());

        $form = $crawler->filterXPath('//form')->form();
        $form['reservation_form[guests]'] = '2';
        $client->submit($form);
        self::assertResponseRedirects();
        self::assertStringContainsString('/fr/dashboard/reserveren/', (string) $client->getResponse()->headers->get('Location'));
        $crawler = $client->followRedirect();
        self::assertStringContainsString('La table est réservée', $crawler->text());
        self::assertStringContainsString('Réservée', $crawler->text());

        $crawler = $client->request('GET', '/fr/dashboard');
        $form = $crawler->filterXPath('//form[contains(@class, "status-form")]')->form();
        $form['status'] = 'cancelled';
        $client->submit($form);
        self::assertResponseRedirects('/fr/dashboard');
        $crawler = $client->followRedirect();
        self::assertStringContainsString('annulée', mb_strtolower($crawler->text()));
        self::assertStringContainsString('réservation', mb_strtolower($crawler->filterXPath('//*[contains(@class, "flash__message")]')->text()));

        $table = $this->entityManager()->find(Table::class, $table->getId());
        self::assertInstanceOf(Table::class, $table);
        $overlap = new Reservation();
        $overlap->setTable($table);
        $overlap->setDate(new \DateTimeImmutable('+2 years'));
        $overlap->setTime(new \DateTimeImmutable('18:00'));
        $overlap->setGuests(2);
        $overlap->setStatus(Reservation::STATUS_CONFIRMED);
        $this->entityManager()->persist($overlap);
        $this->entityManager()->flush();

        $crawler = $client->request('GET', '/fr/dashboard');
        $form = $crawler->filterXPath('//form[contains(@class, "status-form")]')->first()->form();
        $form['status'] = 'confirmed';
        $client->submit($form);
        self::assertResponseRedirects('/fr/dashboard');
        $crawler = $client->followRedirect();
        self::assertStringContainsString('déjà réservée vers 18:00', $crawler->filterXPath('//*[contains(@class, "flash__message")]')->text());
    }

    public function testLegacyPathsAndUnsupportedLocale(): void
    {
        $client = $this->clientWithDatabase();
        $client->request('GET', '/reserveren');
        self::assertResponseIsSuccessful();
        $client->request('GET', '/es/reserveren');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/dashboard');
        self::assertResponseRedirects();
    }


    private function createUser(): User
    {
        $user = new User('staff@example.test');
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        assert($hasher instanceof UserPasswordHasherInterface);
        $user->setPassword($hasher->hashPassword($user, 'password'));
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();

        return $user;
    }
}