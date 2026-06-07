<?php

declare(strict_types=1);

namespace App\Command\DMARC\Domain;

use App\Message\DMARC\ProcessDomain;
use App\Repository\DMARC\DomainRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:dmarc:domain:process',
    description: 'Dispatch ProcessDomain message for a domain',
)]
final class ProcessDomainCommand extends Command
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
        $this->addArgument('id', InputArgument::OPTIONAL, 'Domain UUID');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $id = $input->getArgument('id');
        if (null === $id || '' === trim((string) $id)) {
            $id = $io->ask('Domain ID');
        }

        $domain = $this->domainRepository->find($id);
        if (!$domain) {
            $io->error('Domain does not exist!');

            return Command::FAILURE;
        }

        $domainId = $domain->getId();
        if (!$domainId instanceof Uuid) {
            $io->error('Domain ID is missing.');

            return Command::FAILURE;
        }

        $this->messageBus->dispatch(new ProcessDomain($domainId->toRfc4122()));

        $io->success('ProcessDomain message sent!');

        return Command::SUCCESS;
    }
}
