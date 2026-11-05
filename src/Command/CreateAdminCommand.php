<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin', description: 'Creates an admin user, or resets the password of an existing one')]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Login e-mail')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Password (asked interactively when omitted)')
            ->addOption('editor', null, InputOption::VALUE_NONE, 'Create an editor instead of an admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim((string) $input->getArgument('email')));
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $io->error('Invalid e-mail address.');

            return Command::INVALID;
        }

        $password = $input->getOption('password') ?? $io->askHidden('Password (min. 8 characters)');
        if (!\is_string($password) || mb_strlen($password) < 8) {
            $io->error('The password must have at least 8 characters.');

            return Command::INVALID;
        }

        $user = $this->users->findOneBy(['email' => $email]);
        $isNew = null === $user;
        $user ??= (new User())->setEmail($email);
        $user
            ->setRoles([$input->getOption('editor') ? User::ROLE_EDITOR : User::ROLE_ADMIN])
            ->setEnabled(true)
            ->setPassword($this->hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('%s %s.', $isNew ? 'Created' : 'Updated', $email));

        return Command::SUCCESS;
    }
}
