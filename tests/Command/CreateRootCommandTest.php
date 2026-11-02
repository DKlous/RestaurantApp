<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CreateRootCommand;
use App\Entity\User;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validation;

final class CreateRootCommandTest extends TestCase
{
    private EntityManager $entityManager;
    private UserPasswordHasherInterface $hasher;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 2).'/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->entityManager = new EntityManager($connection, $config);
        (new SchemaTool($this->entityManager))->createSchema([$this->entityManager->getClassMetadata(User::class)]);
        $this->hasher = new UserPasswordHasher(new PasswordHasherFactory([
            User::class => ['algorithm' => 'bcrypt', 'cost' => 4],
        ]));
        $this->tester = new CommandTester(new CreateRootCommand($this->entityManager, $this->hasher, Validation::createValidator()));
    }

    public function testCreatesAdminWithStaffAccessAndPrintsPasswordOnce(): void
    {
        self::assertSame(Command::SUCCESS, $this->tester->execute(['email' => ' root@example.test ']));
        $output = $this->tester->getDisplay();
        self::assertStringContainsString('root@example.test', $output);
        self::assertMatchesRegularExpression('/Wachtwoord: ([a-f0-9]{64})/', $output);
        preg_match('/Wachtwoord: ([a-f0-9]{64})/', $output, $matches);
        $password = $matches[1] ?? '';
        self::assertSame(1, substr_count($output, $password));

        $this->entityManager->clear();
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'root@example.test']);
        self::assertInstanceOf(User::class, $user);
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertContains('ROLE_STAFF', $user->getRoles());
        self::assertNotSame($password, $user->getPassword());
        self::assertTrue($this->hasher->isPasswordValid($user, $password));
        self::assertFalse($this->hasher->isPasswordValid($user, 'wrong-password'));

        self::assertSame(Command::SUCCESS, $this->tester->execute(['email' => 'root@example.test']));
        self::assertStringNotContainsString('Wachtwoord:', $this->tester->getDisplay());
        self::assertSame(1, $this->entityManager->getRepository(User::class)->count([]));
        self::assertTrue($this->hasher->isPasswordValid($user, $password));
    }

    public function testExistingNonAdminIsNotModified(): void
    {
        $existing = new User('staff@example.test');
        $existing->setPassword($this->hasher->hashPassword($existing, 'existing-password'));
        $this->entityManager->persist($existing);
        $this->entityManager->flush();
        $originalHash = $existing->getPassword();

        self::assertSame(Command::SUCCESS, $this->tester->execute(['email' => 'staff@example.test']));
        self::assertStringNotContainsString('Wachtwoord:', $this->tester->getDisplay());
        $this->entityManager->clear();
        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'staff@example.test']);
        self::assertInstanceOf(User::class, $existing);
        self::assertSame($originalHash, $existing->getPassword());
        self::assertSame(['ROLE_STAFF'], $existing->getRoles());
        self::assertSame(1, $this->entityManager->getRepository(User::class)->count([]));
    }

    public function testRejectsInvalidAndOversizedEmailAddresses(): void
    {
        foreach (['', 'not-an-email', str_repeat('a', 170).'@example.test', "root@example.test\nother@example.test"] as $email) {
            self::assertSame(Command::INVALID, $this->tester->execute(['email' => $email]));
            self::assertStringNotContainsString('Wachtwoord:', $this->tester->getDisplay());
        }

        self::assertSame(0, $this->entityManager->getRepository(User::class)->count([]));
    }

    public function testQuietAndSilentOutputDoNotCreateAnAccount(): void
    {
        foreach ([OutputInterface::VERBOSITY_QUIET, OutputInterface::VERBOSITY_SILENT] as $verbosity) {
            self::assertSame(Command::FAILURE, $this->tester->execute(['email' => 'root@example.test'], ['verbosity' => $verbosity]));
        }

        self::assertSame(0, $this->entityManager->getRepository(User::class)->count([]));
    }

    public function testConcurrentDuplicateDoesNotPrintPassword(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush')->willThrowException($this->createStub(UniqueConstraintViolationException::class));
        $tester = new CommandTester(new CreateRootCommand($entityManager, $this->hasher, Validation::createValidator()));

        self::assertSame(Command::SUCCESS, $tester->execute(['email' => 'root@example.test']));
        self::assertStringNotContainsString('Wachtwoord:', $tester->getDisplay());
    }
}