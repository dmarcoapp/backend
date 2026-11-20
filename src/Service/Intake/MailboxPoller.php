<?php

declare(strict_types=1);

namespace App\Service\Intake;

use App\Entity\Intake\Mailbox;
use App\Entity\Intake\MailboxLogEntry;
use App\Enum\Email\ReportSource;
use App\Enum\Intake\AfterImport;
use App\Service\DMARC\ReportXMLProcessor;
use App\Service\Security\UnreadableSecretException;
use DirectoryTree\ImapEngine\FolderInterface;
use DirectoryTree\ImapEngine\Mailbox as ImapMailbox;
use DirectoryTree\ImapEngine\MessageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Reads the reports that arrived in a mailbox since the last look.
 *
 * Messages are found by UID, from where the last run stopped, so reading them
 * in a mail client changes nothing. They are fetched with BODY.PEEK, which
 * leaves them unread. A message that is not a report is never touched, and a
 * report email is marked, moved or deleted only once its report is stored.
 */
final readonly class MailboxPoller
{
    /**
     * The largest message looked at, the limit the mail gateway applied.
     */
    public const int MAX_MESSAGE_BYTES = 10 * 1024 * 1024;

    /**
     * A long backlog is read over several runs, so one mailbox cannot keep the
     * worker from everything else.
     */
    public const int MAX_MESSAGES_PER_RUN = 100;

    private const int MAX_ERROR_LENGTH = 500;
    private const int LOCK_TTL_SECONDS = 600;

    public function __construct(
        private MailboxConnector $connector,
        private ReportArchiveExtractor $extractor,
        private ReportXMLProcessor $xmlProcessor,
        private ReportIngestor $ingestor,
        private EntityManagerInterface $entityManager,
        private LockFactory $lockFactory,
        private LoggerInterface $logger,
    ) {}

    public function poll(Mailbox $mailbox): PollResult
    {
        $result = new PollResult();

        $lock = $this->lockFactory->createLock('mailbox-poll-'.$mailbox->getId()->toRfc4122(), self::LOCK_TTL_SECONDS);
        if (!$lock->acquire()) {
            $result->busy = true;

            return $result;
        }

        $client = null;

        try {
            $client = $this->connector->connect($mailbox);
            $this->read($client, $mailbox, $result);
            $mailbox->recordSuccess(self::now());

            if ($result->imported > 0 || $result->skipped > 0) {
                $this->log($mailbox, MailboxLogEntry::INFO, sprintf(
                    'Read %d %s: %d %s stored, %d skipped.',
                    $result->looked,
                    1 === $result->looked ? 'message' : 'messages',
                    $result->imported,
                    1 === $result->imported ? 'report' : 'reports',
                    $result->skipped,
                ));
            }
        } catch (\Throwable $e) {
            $result->error = self::describe($e);
            $mailbox->recordFailure(self::now(), $result->error);
            $this->log($mailbox, MailboxLogEntry::ERROR, $result->error);
            $this->logger->warning('A mailbox could not be read.', [
                'exception' => $e,
                'mailbox' => $mailbox->getId()->toRfc4122(),
            ]);
        } finally {
            try {
                $client?->disconnect();
            } catch (\Throwable) {
                // The connection is going away either way.
            }

            $this->entityManager->flush();
            $lock->release();
        }

        return $result;
    }

    private function read(ImapMailbox $client, Mailbox $mailbox, PollResult $result): void
    {
        $folder = $client->folders()->find($mailbox->getFolder());
        if (null === $folder) {
            throw new MailboxException(sprintf('The folder "%s" does not exist.', $mailbox->getFolder()));
        }

        $status = $folder->status();
        $uidValidity = (int) ($status['UIDVALIDITY'] ?? 0);
        $uidNext = (int) ($status['UIDNEXT'] ?? 1);

        $lastUid = $mailbox->getLastUid();
        $firstRun = null === $lastUid || $mailbox->getUidValidity() !== $uidValidity;
        if ($firstRun && null !== $mailbox->getUidValidity()) {
            $this->log($mailbox, MailboxLogEntry::WARNING, 'The server numbered the folder anew, so it is read again from the start of the import window. Reports already stored are recognized.');
        }

        $from = $firstRun ? 1 : $lastUid + 1;
        $query = $folder->messages()->leaveUnread()->withSize()->withoutHeaders()->withoutBody()->uid($from, INF);
        if ($firstRun && $mailbox->getFirstImportDays() > 0) {
            $query->since(self::now()->modify(sprintf('-%d days', $mailbox->getFirstImportDays())));
        }

        $sizes = [];
        foreach ($query->get() as $message) {
            // A UID range ending in * always matches the newest message, even
            // one below the range, so the range is checked here again.
            if ($message->uid() >= $from) {
                $sizes[$message->uid()] = $message->size();
            }
        }
        ksort($sizes);

        if (count($sizes) > self::MAX_MESSAGES_PER_RUN) {
            $sizes = array_slice($sizes, 0, self::MAX_MESSAGES_PER_RUN, true);
            $result->more = true;
        }

        foreach ($sizes as $uid => $size) {
            ++$result->looked;
            $this->readMessage($client, $folder, $mailbox, $uid, $size, $uidValidity, $result);
            $mailbox->setCursor($uidValidity, $uid);
            $this->entityManager->flush();
        }

        if (!$result->more) {
            $mailbox->setCursor($uidValidity, max($mailbox->getLastUid() ?? 0, $uidNext - 1));
        }
    }

    private function readMessage(ImapMailbox $client, FolderInterface $folder, Mailbox $mailbox, int $uid, ?int $size, int $uidValidity, PollResult $result): void
    {
        if (null !== $size && $size > self::MAX_MESSAGE_BYTES) {
            $this->skip($mailbox, $result, $uid, 'Skipped a message larger than 10 MB.');

            return;
        }

        $message = $folder->messages()->leaveUnread()->withHeaders()->withBody()->find($uid);
        if (null === $message) {
            ++$result->skipped;

            return;
        }

        if (strlen((string) $message) > self::MAX_MESSAGE_BYTES) {
            $this->skip($mailbox, $result, $uid, 'Skipped a message larger than 10 MB.');

            return;
        }

        try {
            $attachments = array_values(array_filter(
                $message->attachments(),
                static fn ($attachment): bool => ReportArchiveExtractor::isReportCandidate($attachment->filename(), $attachment->contentType()),
            ));
        } catch (\Throwable $e) {
            $this->logger->info('A message in a mailbox could not be parsed.', ['exception' => $e, 'uid' => $uid]);
            $this->skip($mailbox, $result, $uid, 'Skipped a message that could not be read.');

            return;
        }

        // Ordinary mail, which is none of DMARCo's business.
        if ([] === $attachments) {
            ++$result->skipped;

            return;
        }

        if (count($attachments) > 1) {
            $this->skip($mailbox, $result, $uid, 'Skipped a message with more than one report attachment.');

            return;
        }

        $attachment = $attachments[0];

        try {
            $xml = $this->extractor->extract($attachment->contents());
        } catch (ReportArchiveException $e) {
            $this->skip($mailbox, $result, $uid, 'Skipped an attachment: '.$e->getMessage());

            return;
        }

        if (!$this->xmlProcessor->validate($xml)) {
            $this->skip($mailbox, $result, $uid, 'Skipped an attachment that is not a valid DMARC aggregate report.');

            return;
        }

        $outcome = $this->ingestor->ingest(new IncomingReport(
            source: ReportSource::IMAP,
            messageId: $message->messageId() ?? sprintf('<imap-%s-%d-%d@dmarco>', $mailbox->getId()->toRfc4122(), $uidValidity, $uid),
            fromAddress: $message->from()?->email() ?? 'unknown',
            toAddress: $mailbox->getReportAddress() ?? ($message->to()[0] ?? null)?->email() ?? $mailbox->getUsername(),
            receivedAt: \DateTimeImmutable::createFromInterface($message->date() ?? self::now())->setTimezone(new \DateTimeZone('UTC')),
            filename: $attachment->filename() ?? 'report.xml',
            xml: $xml,
            mailbox: $mailbox,
            imapUid: $uid,
        ));

        match ($outcome) {
            IngestOutcome::ACCEPTED => ++$result->imported,
            IngestOutcome::ALREADY_RECEIVED => ++$result->alreadyReceived,
            IngestOutcome::BLOCKED => $this->skip($mailbox, $result, $uid, 'Skipped a report from a sender on the blocklist.', MailboxLogEntry::INFO),
        };

        // A blocked report stays where it is, as if DMARCo had not seen it.
        if (IngestOutcome::BLOCKED !== $outcome) {
            $this->afterImport($client, $message, $mailbox);
        }
    }

    private function afterImport(ImapMailbox $client, MessageInterface $message, Mailbox $mailbox): void
    {
        match ($mailbox->getAfterImport()) {
            AfterImport::LEAVE => null,
            AfterImport::MARK_SEEN => $message->markSeen(),
            // Never expunged: that would remove every message marked deleted.
            AfterImport::DELETE => $message->markDeleted(),
            AfterImport::MOVE => $this->move($client, $message, (string) $mailbox->getMoveToFolder()),
        };
    }

    private function move(ImapMailbox $client, MessageInterface $message, string $target): void
    {
        if (in_array('MOVE', $client->capabilities(), true)) {
            $client->connection()->move($target, $message->uid());

            return;
        }

        // Without MOVE, a copy and a deletion mark do the same, short of the
        // expunge, which is left to the server or the mail client.
        $client->connection()->copy($target, $message->uid());
        $message->markDeleted();
    }

    private function skip(Mailbox $mailbox, PollResult $result, int $uid, string $reason, string $level = MailboxLogEntry::WARNING): void
    {
        ++$result->skipped;
        $this->log($mailbox, $level, $reason, ['uid' => $uid]);
    }

    /**
     * @param array<string, null|scalar> $context
     */
    private function log(Mailbox $mailbox, string $level, string $message, array $context = []): void
    {
        $this->entityManager->persist(new MailboxLogEntry($mailbox, $level, $message, $context));
    }

    /**
     * What went wrong, in words fit for the dashboard. The library's messages
     * redact what was sent, so no password reaches them.
     */
    private static function describe(\Throwable $e): string
    {
        $message = match (true) {
            $e instanceof UnreadableSecretException => 'The stored password cannot be read with this APP_ENCRYPTION_KEY. Enter it again.',
            default => trim($e->getMessage()),
        };

        return mb_substr('' === $message ? $e::class : $message, 0, self::MAX_ERROR_LENGTH);
    }

    private static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
