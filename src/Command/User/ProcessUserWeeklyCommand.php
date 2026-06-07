<?php

declare(strict_types=1);

namespace App\Command\User;

use App\Message\User\ProcessUserWeekly;
use App\Repository\User\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:user:process-weekly',
    description: 'Dispatch ProcessUserWeekly message for a user',
)]
final class ProcessUserWeeklyCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::OPTIONAL, 'User UUID');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $id = $input->getArgument('id');
        if (null === $id || '' === trim((string) $id)) {
            $id = $io->ask('User ID');
        }

        $user = $this->userRepository->find($id);
        if (!$user) {
            $io->error('User does not exist!');

            return Command::FAILURE;
        }

        $userId = $user->getId();
        if (!$userId instanceof Uuid) {
            $io->error('User ID is missing.');

            return Command::FAILURE;
        }

        $this->messageBus->dispatch(new ProcessUserWeekly($userId->toRfc4122()));

        $io->success('ProcessUserWeekly message sent!');

        return Command::SUCCESS;
    }
}
