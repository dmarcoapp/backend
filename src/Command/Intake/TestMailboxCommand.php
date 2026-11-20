<?php

declare(strict_types=1);

namespace App\Command\Intake;

use App\Repository\Intake\MailboxRepository;
use App\Service\Intake\MailboxTester;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mailbox:test',
    description: 'Sign in to a mailbox and look at its folders, without reading any message',
)]
final class TestMailboxCommand extends Command
{
    use MailboxCommandTrait;

    public function __construct(
        private readonly MailboxRepository $mailboxes,
        private readonly MailboxTester $tester,
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

        $result = $this->tester->test($mailbox);
        if (!$result->ok) {
            $io->error((string) $result->error);

            return Command::FAILURE;
        }

        $io->success(sprintf('Signed in. "%s" holds %d messages.', $mailbox->getFolder(), (int) $result->messages));

        return Command::SUCCESS;
    }
}
