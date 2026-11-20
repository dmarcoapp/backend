<?php

declare(strict_types=1);

namespace App\Service\Intake;

use App\Entity\Intake\Mailbox;
use App\Enum\Intake\AfterImport;
use App\Service\Security\UnreadableSecretException;
use DirectoryTree\ImapEngine\FolderInterface;
use Psr\Log\LoggerInterface;

/**
 * Signs in to a mailbox and looks at its folders, without reading or changing
 * any message, to tell whether the settings work.
 */
final readonly class MailboxTester
{
    private const int MAX_FOLDERS = 50;
    private const int MAX_ERROR_LENGTH = 500;

    public function __construct(
        private MailboxConnector $connector,
        private LoggerInterface $logger,
    ) {}

    public function test(Mailbox $mailbox): MailboxTestResult
    {
        $client = null;

        try {
            $client = $this->connector->connect($mailbox);

            $folders = array_slice(array_map(
                static fn (FolderInterface $folder): string => $folder->path(),
                $client->folders()->get()->all(),
            ), 0, self::MAX_FOLDERS);

            $folder = $client->folders()->find($mailbox->getFolder());
            if (null === $folder) {
                return new MailboxTestResult(false, sprintf('Signed in, but the folder "%s" does not exist.', $mailbox->getFolder()), folders: $folders);
            }

            if (AfterImport::MOVE === $mailbox->getAfterImport() && null === $client->folders()->find((string) $mailbox->getMoveToFolder())) {
                return new MailboxTestResult(false, sprintf('Signed in, but the folder "%s" to move reports to does not exist.', (string) $mailbox->getMoveToFolder()), folders: $folders);
            }

            $status = $folder->status();

            return new MailboxTestResult(true, messages: (int) ($status['MESSAGES'] ?? 0), folders: $folders);
        } catch (UnreadableSecretException) {
            return new MailboxTestResult(false, 'The stored password cannot be read with this APP_ENCRYPTION_KEY. Enter it again.');
        } catch (\Throwable $e) {
            $this->logger->info('A mailbox test failed.', ['exception' => $e, 'mailbox' => $mailbox->getId()->toRfc4122()]);

            return new MailboxTestResult(false, mb_substr(trim($e->getMessage()) ?: $e::class, 0, self::MAX_ERROR_LENGTH));
        } finally {
            try {
                $client?->disconnect();
            } catch (\Throwable) {
                // The connection is going away either way.
            }
        }
    }
}
