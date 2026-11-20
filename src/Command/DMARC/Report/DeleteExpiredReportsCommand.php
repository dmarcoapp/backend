<?php

declare(strict_types=1);

namespace App\Command\DMARC\Report;

use App\Message\DMARC\DeleteExpiredReports;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:dmarc:report:delete-expired',
    description: 'Delete the reports older than the retention period',
)]
final class DeleteExpiredReportsCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->messageBus->dispatch(new DeleteExpiredReports());

        new SymfonyStyle($input, $output)->success('Queued the deletion of expired reports.');

        return Command::SUCCESS;
    }
}
