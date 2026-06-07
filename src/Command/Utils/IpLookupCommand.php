<?php

declare(strict_types=1);

namespace App\Command\Utils;

use App\Service\IpLookup\IpLookupInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:utils:ip-lookup',
    description: 'IP address lookup'
)]
final class IpLookupCommand extends Command
{
    public function __construct(
        private readonly IpLookupInterface $ipLookup,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument(
            'ip',
            InputArgument::REQUIRED,
            'IPv4 or IPv6 address to look up'
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $ip = (string) $input->getArgument('ip');

        try {
            $result = $this->ipLookup->lookup($ip);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->title(sprintf('Lookup results for %s', $ip));

        $io->section('ISP / Network');
        $io->definitionList(
            ['Name' => $result['name'] ?? '—'],
            ['Country' => $result['country'] ?? '—'],
        );

        $io->section('Contact emails');
        $io->definitionList(
            ['Abuse email' => $result['email']['abuse'] ?? '—'],
            ['Technical email' => $result['email']['tech'] ?? '—'],
        );

        return Command::SUCCESS;
    }
}
