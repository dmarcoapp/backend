<?php

declare(strict_types=1);

namespace App\Command\DMARC\Report;

use App\Message\DMARC\ProcessReport;
use App\Repository\DMARC\ReportRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:dmarc:report:process',
    description: 'Process a Report',
)]
final class ProcessReportCommand extends Command
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
        $this->addArgument('id', InputArgument::OPTIONAL, 'Report UUID');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $id = $input->getArgument('id');
        if (null === $id || '' === trim((string) $id)) {
            $id = $io->ask('Report ID');
        }
        $report = $this->reportRepository->find($id);
        if (!$report) {
            $io->error('Report does not exist!');

            return Command::FAILURE;
        }

        $reportId = $report->getId();
        if (!$reportId instanceof Uuid) {
            $io->error('Report ID is missing.');

            return Command::FAILURE;
        }

        $this->messageBus->dispatch(new ProcessReport($reportId->toRfc4122()));

        $io->success('ProcessReport message sent!');

        return Command::SUCCESS;
    }
}
