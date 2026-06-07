<?php

declare(strict_types=1);

namespace App\Command\DMARC\Domain;

use App\Message\DMARC\ProcessDomain;
use App\Repository\DMARC\DomainRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:dmarc:domain:process-bulk',
    description: 'Dispatch ProcessDomain messages for domains due for processing',
)]
final class ProcessDomainBulkCommand extends Command
{
    public function __construct(
        private readonly DomainRepository $domainRepository,
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

        $domains = $this->domainRepository->getDomainsForRegularProcessing();
        if (null !== $limit) {
            $domains = array_slice($domains, 0, $limit);
        }

        if (!$domains) {
            $io->success('No domains found for processing.');

            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($domains as $domain) {
            $domainId = $domain->getId();
            if (!$domainId instanceof Uuid) {
                continue;
            }

            $this->messageBus->dispatch(new ProcessDomain($domainId->toRfc4122()));
            ++$count;
        }

        $io->success(sprintf('Dispatched %d ProcessDomain message(s).', $count));

        return Command::SUCCESS;
    }
}
