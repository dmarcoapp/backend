<?php

declare(strict_types=1);

namespace App\Command\DMARC\Domain;

use App\Message\DMARC\ProcessNewDomains;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:dmarc:domain:process-new',
    description: 'Check the DNS of the domains that have not been checked yet',
)]
final class ProcessNewDomainsCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->messageBus->dispatch(new ProcessNewDomains());

        new SymfonyStyle($input, $output)->success('Queued the check of new domains.');

        return Command::SUCCESS;
    }
}
