<?php

declare(strict_types=1);

namespace App\Command\User;

use App\Repository\User\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:reset-password',
    description: 'Set a new password for a user',
)]
final class ResetUserPasswordCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $io->ask('Email');
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error('User with email does not exist!');

            return Command::FAILURE;
        }

        $plainPassword = $io->askHidden('New password', CreateUserCommand::validatePassword(...));
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->userRepository->save($user);

        $io->success('Password changed!');

        return Command::SUCCESS;
    }
}
