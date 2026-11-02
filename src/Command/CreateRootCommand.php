<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:create-root', description: 'Maakt een nieuw rootaccount aan zonder bestaande accounts te wijzigen.')]
class CreateRootCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'E-mailadres voor het nieuwe rootaccount');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($output->isQuiet() || $output->isSilent()) {
            $io->error('Stille uitvoer is niet toegestaan: het wachtwoord moet eenmaal getoond kunnen worden.');

            return Command::FAILURE;
        }

        $email = $input->getArgument('email');
        if (!is_string($email)) {
            $io->error('Geef een geldig e-mailadres van maximaal 180 tekens op.');

            return Command::INVALID;
        }

        $email = trim($email);
        if (count($this->validator->validate($email, [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)])) > 0) {
            $io->error('Geef een geldig e-mailadres van maximaal 180 tekens op.');

            return Command::INVALID;
        }

        if (null !== $this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            $io->warning('Dit account bestaat al en is niet gewijzigd.');

            return Command::SUCCESS;
        }

        $password = bin2hex(random_bytes(32));
        $user = new User($email);
        $user->addRole('ROLE_ADMIN');
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            $io->warning('Dit account bestaat al en is niet gewijzigd.');

            return Command::SUCCESS;
        }

        $output->writeln(sprintf('Rootaccount: %s', $email), OutputInterface::OUTPUT_RAW);
        $output->writeln(sprintf('Wachtwoord: %s', $password), OutputInterface::OUTPUT_RAW);
        $io->note('Bewaar het wachtwoord veilig; het wordt niet opnieuw getoond.');

        return Command::SUCCESS;
    }
}