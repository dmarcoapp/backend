<?php

declare(strict_types=1);

namespace App\Command\User;

use App\Repository\User\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// The way back in after losing the authenticator app. Signing in then needs only
// the password, until two-factor authentication is set up again in the profile.
#[AsCommand(
    name: 'app:user:disable-two-factor',
    description: 'Turn off two-factor authentication for a user',
)]
final class DisableTwoFactorCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
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

        $user
            ->setTwoFactorMethod(null)
            ->setTwoFactorAppSecret(null)
        ;
        $this->userRepository->save($user);

        $io->success('Two-factor authentication is off.');

        return Command::SUCCESS;
    }
}
