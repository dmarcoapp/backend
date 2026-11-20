<?php

declare(strict_types=1);

namespace App\Command\Intake;

use App\DTO\Input\Intake\MailboxInput;
use App\Repository\Intake\MailboxRepository;
use App\Service\Intake\MailboxEditor;
use App\Service\Intake\MailboxTester;
use App\Service\Platform\PlatformSettingsProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Adds a mailbox to fetch reports from. The password never goes on the command
 * line, where other users of the host could read it: it comes from the
 * environment variable DMARCO_MAILBOX_PASSWORD, or from a hidden prompt.
 */
#[AsCommand(
    name: 'app:mailbox:add',
    description: 'Add a mailbox to fetch DMARC reports from over IMAP',
)]
final class AddMailboxCommand extends Command
{
    public const string PASSWORD_VARIABLE = 'DMARCO_MAILBOX_PASSWORD';

    public function __construct(
        private readonly PlatformSettingsProvider $settings,
        private readonly MailboxEditor $editor,
        private readonly MailboxRepository $mailboxes,
        private readonly MailboxTester $tester,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'A name to tell the mailbox by')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'IMAP server')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Port, 993 with TLS and 143 with STARTTLS unless given')
            ->addOption('security', null, InputOption::VALUE_REQUIRED, 'tls or starttls', 'tls')
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Username')
            ->addOption('folder', null, InputOption::VALUE_REQUIRED, 'Folder the reports arrive in', 'INBOX')
            ->addOption('report-address', null, InputOption::VALUE_REQUIRED, 'The address in the DMARC records that delivers here')
            ->addOption('after-import', null, InputOption::VALUE_REQUIRED, 'mark_seen, move, delete or leave', 'mark_seen')
            ->addOption('move-to-folder', null, InputOption::VALUE_REQUIRED, 'Folder to move reports to, with --after-import=move')
            ->addOption('first-import-days', null, InputOption::VALUE_REQUIRED, 'How many days back the first read goes, 0 for all', '30')
            ->addOption('test', null, InputOption::VALUE_NONE, 'Sign in once the mailbox is added, and keep it switched off if that fails')
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->settings->isLegacyMode()) {
            $io->error('Mailboxes need APP_ENCRYPTION_KEY. Run the installer again to generate it.');

            return Command::FAILURE;
        }

        $name = $this->option($input, 'name') ?? $io->ask('Name', 'Reports');
        $host = $this->option($input, 'host') ?? $io->ask('IMAP server');
        $username = $this->option($input, 'username') ?? $io->ask('Username');

        $password = getenv(self::PASSWORD_VARIABLE);
        if (!is_string($password) || '' === $password) {
            $password = $input->isInteractive() ? $io->askHidden('Password') : null;
        }

        $port = $this->option($input, 'port');
        $firstImportDays = $this->option($input, 'first-import-days');
        $reportAddress = $this->option($input, 'report-address');
        if (null === $reportAddress && is_string($username) && false !== filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $reportAddress = $username;
        }

        $mailboxInput = new MailboxInput(
            name: is_string($name) ? $name : null,
            host: is_string($host) ? $host : null,
            port: null === $port ? null : (int) $port,
            security: $this->option($input, 'security'),
            username: is_string($username) ? $username : null,
            password: is_string($password) ? $password : null,
            folder: $this->option($input, 'folder'),
            reportAddress: $reportAddress,
            afterImport: $this->option($input, 'after-import'),
            moveToFolder: $this->option($input, 'move-to-folder'),
            firstImportDays: null === $firstImportDays ? null : (int) $firstImportDays,
        );

        $violations = $this->validator->validate($mailboxInput, groups: ['Default', MailboxInput::CREATE]);
        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $io->error(sprintf('%s: %s', $violation->getPropertyPath(), (string) $violation->getMessage()));
            }

            return Command::INVALID;
        }

        try {
            $mailbox = $this->editor->create($mailboxInput);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $result = $input->getOption('test') ? $this->tester->test($mailbox) : null;
        if (null !== $result && !$result->ok) {
            $mailbox->setEnabled(false);
        }

        $this->mailboxes->save($mailbox);

        if (null !== $result && !$result->ok) {
            $io->warning([
                sprintf('Added "%s", switched off, because signing in failed: %s', $mailbox->getName(), (string) $result->error),
                'Fix it on the Platform page of the dashboard, and switch it on there.',
            ]);

            return Command::FAILURE;
        }

        $io->success(sprintf('Added "%s". DMARCo reads it every %d minutes.', $mailbox->getName(), $mailbox->getPollIntervalMinutes()));

        return Command::SUCCESS;
    }

    private function option(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
