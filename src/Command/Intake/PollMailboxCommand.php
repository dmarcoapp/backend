<?php

declare(strict_types=1);

namespace App\Command\Intake;

use App\Repository\Intake\MailboxRepository;
use App\Service\Intake\MailboxPoller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mailbox:poll',
    description: 'Read a mailbox now, instead of at its next turn',
)]
final class PollMailboxCommand extends Command
{
    use MailboxCommandTrait;

    public function __construct(
        private readonly MailboxRepository $mailboxes,
        private readonly MailboxPoller $poller,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('mailbox', InputArgument::REQUIRED, 'ID or name of the mailbox');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $mailbox = $this->findMailbox($this->mailboxes, (string) $input->getArgument('mailbox'));
        if (null === $mailbox) {
            $io->error('There is no such mailbox. See app:mailbox:list.');

            return Command::FAILURE;
        }

        $result = $this->poller->poll($mailbox);
        if ($result->busy) {
            $io->warning('The mailbox is being read already.');

            return Command::SUCCESS;
        }

        if (!$result->succeeded()) {
            $io->error((string) $result->error);

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Read %d messages: %d reports stored, %d already stored, %d skipped.%s',
            $result->looked,
            $result->imported,
            $result->alreadyReceived,
            $result->skipped,
            $result->more ? ' More are waiting, run it again.' : '',
        ));

        return Command::SUCCESS;
    }
}
