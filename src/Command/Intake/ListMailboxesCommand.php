<?php

declare(strict_types=1);

namespace App\Command\Intake;

use App\Repository\Intake\MailboxRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mailbox:list',
    description: 'List the mailboxes DMARC reports are fetched from',
)]
final class ListMailboxesCommand extends Command
{
    public function __construct(
        private readonly MailboxRepository $mailboxes,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = [];
        foreach ($this->mailboxes->findAllByName() as $mailbox) {
            $rows[] = [
                $mailbox->getId()->toRfc4122(),
                $mailbox->getName(),
                sprintf('%s@%s:%d', $mailbox->getUsername(), $mailbox->getHost(), $mailbox->getPort()),
                $mailbox->getFolder(),
                $mailbox->isEnabled() ? 'yes' : 'no',
                $mailbox->getLastSuccessAt()?->format('Y-m-d H:i') ?? '-',
                $mailbox->getLastError() ?? '',
            ];
        }

        $io = new SymfonyStyle($input, $output);
        if ([] === $rows) {
            $io->writeln('No mailboxes yet. Add one with app:mailbox:add, or on the Platform page.');

            return Command::SUCCESS;
        }

        $io->table(['ID', 'Name', 'Account', 'Folder', 'On', 'Last read', 'Last error'], $rows);

        return Command::SUCCESS;
    }
}
