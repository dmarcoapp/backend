<?php

declare(strict_types=1);

namespace App\Command\Email;

use App\Message\Email\ProcessEmail;
use App\Repository\Email\EmailRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:email:process-bulk',
    description: 'Dispatch ProcessEmail messages for emails without reports',
)]
final class ProcessEmailBulkCommand extends Command
{
    public function __construct(
        private readonly EmailRepository $emailRepository,
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

        $emailIds = $this->emailRepository->getEmailIdsWithoutReport($limit);
        if (!$emailIds) {
            $io->success('No emails found for processing.');

            return Command::SUCCESS;
        }

        foreach ($emailIds as $emailId) {
            $this->messageBus->dispatch(new ProcessEmail((string) $emailId));
        }

        $io->success(sprintf('Dispatched %d ProcessEmail message(s).', count($emailIds)));

        return Command::SUCCESS;
    }
}
