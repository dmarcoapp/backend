<?php

declare(strict_types=1);

namespace App\Command\User;

use App\Entity\User\User;
use App\Repository\User\UserRepository;
use App\Service\User\UserDeleteProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:delete',
    description: 'Delete a user',
)]
final class DeleteUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserDeleteProcessor $userDeleteProcessor,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $io->ask('Email');
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $io->error('User with email does not exist!');

            return Command::FAILURE;
        }

        try {
            $this->userDeleteProcessor->process($user);
        } catch (\Throwable) {
            $io->error('Failed to delete user.');

            return Command::FAILURE;
        }

        $io->success('User deleted!');

        return Command::SUCCESS;
    }
}
