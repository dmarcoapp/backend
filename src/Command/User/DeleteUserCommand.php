<?php

declare(strict_types=1);

namespace App\Command\User;

use App\Entity\User\User;
use App\Repository\User\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:delete',
    description: 'Delete the account, so that a new one can be created. The reports stay',
)]
final class DeleteUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
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
            $this->userRepository->delete($user);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to delete user.', [
                'exception' => $e,
                'user_id' => $user->getId()?->toRfc4122(),
            ]);
            $io->error('Failed to delete user.');

            return Command::FAILURE;
        }

        $io->success('User deleted!');

        return Command::SUCCESS;
    }
}
