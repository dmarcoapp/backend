<?php

declare(strict_types=1);

namespace App\Command\DMARC\Report;

use App\Message\DMARC\ProcessReport;
use App\Repository\DMARC\ReportRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:dmarc:report:process-bulk',
    description: 'Dispatch ProcessReport messages for unprocessed reports',
)]
final class ProcessReportBulkCommand extends Command
{
    public function __construct(
        private readonly ReportRepository $reportRepository,
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

        $reportIds = $this->reportRepository->getUnprocessedReportIds($limit);
        if (!$reportIds) {
            $io->success('No unprocessed reports found.');

            return Command::SUCCESS;
        }

        foreach ($reportIds as $reportId) {
            $this->messageBus->dispatch(new ProcessReport((string) $reportId));
        }

        $io->success(sprintf('Dispatched %d ProcessReport message(s).', count($reportIds)));

        return Command::SUCCESS;
    }
}
