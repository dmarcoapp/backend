<?php

declare(strict_types=1);

namespace App\Command\User;

use App\Message\User\SendWeeklyOverview;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:weekly-overview:send',
    description: 'Send the weekly overview to the account',
)]
final class SendWeeklyOverviewCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->messageBus->dispatch(new SendWeeklyOverview());

        new SymfonyStyle($input, $output)->success('Queued the weekly overview.');

        return Command::SUCCESS;
    }
}
