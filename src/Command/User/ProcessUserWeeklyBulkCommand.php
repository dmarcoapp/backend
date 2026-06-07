<?php

declare(strict_types=1);

namespace App\Command\User;

use App\Message\User\ProcessUserWeekly;
use App\Repository\User\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:user:process-weekly-bulk',
    description: 'Dispatch ProcessUserWeekly messages for all users',
)]
final class ProcessUserWeeklyBulkCommand extends Command
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
        $this->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit number of dispatched messages');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = $input->getOption('limit');
        $limit = null !== $limit ? max(1, (int) $limit) : null;

        $userIds = $this->userRepository->getAllUserIds($limit);
        if (!$userIds) {
            $io->success('No users found for processing.');

            return Command::SUCCESS;
        }

        foreach ($userIds as $userId) {
            $this->messageBus->dispatch(new ProcessUserWeekly((string) $userId));
        }

        $io->success(sprintf('Dispatched %d ProcessUserWeekly message(s).', count($userIds)));

        return Command::SUCCESS;
    }
}
