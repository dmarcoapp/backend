<?php

declare(strict_types=1);

namespace App\Service\Intake;

use App\Entity\Email\Email;
use App\Message\Email\ProcessEmail;
use App\Repository\Email\EmailRepository;
use App\Service\User\BlocklistMatcher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;

/**
 * Where every report enters, from the mail gateway, the API or a mailbox:
 * the same duplicate check and blocklist apply, and the report goes the same
 * way from here on.
 */
final readonly class ReportIngestor
{
    private const int MAX_FIELD_LENGTH = 255;

    public function __construct(
        private EmailRepository $emailRepository,
        private BlocklistMatcher $blocklistMatcher,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {}

    public function ingest(IncomingReport $report): IngestOutcome
    {
        $messageId = self::field($report->messageId);
        $toAddress = self::field($report->toAddress);
        $fromAddress = self::field(self::normalizeAddress($report->fromAddress));

        if (null !== $this->emailRepository->findOneBy(['messageId' => $messageId, 'toAddress' => $toAddress])) {
            $this->logger->info('Inbound email already handled.', [
                'messageId' => $messageId,
                'toAddress' => $toAddress,
            ]);

            return IngestOutcome::ALREADY_RECEIVED;
        }

        if ($this->blocklistMatcher->isBlocked($fromAddress)) {
            $this->logger->info('Inbound email blocked by the blocklist.', [
                'fromAddress' => $fromAddress,
                'messageId' => $messageId,
            ]);

            return IngestOutcome::BLOCKED;
        }

        $email = new Email()
            ->setCreatedAt($report->receivedAt)
            ->setFromAddress($fromAddress)
            ->setToAddress($toAddress)
            ->setMessageId($messageId)
            ->setAttachmentFilename(self::xmlFilename($report->filename))
            ->setAttachmentContentType('application/xml')
            ->setAttachmentContent($report->xml)
            ->setSource($report->source)
            ->setMailbox($report->mailbox, $report->imapUid)
            ->setApiKey($report->apiKey)
        ;

        $this->emailRepository->save($email);
        $emailId = $email->getId();
        if (null === $emailId) {
            throw new \LogicException('Persisted email ID is missing.');
        }

        $this->messageBus->dispatch(new ProcessEmail(emailId: $emailId->toRfc4122()));

        return IngestOutcome::ACCEPTED;
    }

    public static function normalizeAddress(string $address): string
    {
        try {
            return Address::create($address)->getAddress();
        } catch (\InvalidArgumentException) {
            return trim($address);
        }
    }

    /**
     * The name of the unpacked report: what the report was called, without its
     * archive extension and its path, ending in .xml.
     */
    public static function xmlFilename(string $filename): string
    {
        $name = basename(str_replace('\\', '/', trim($filename)));
        $name = (string) preg_replace('/\.(gz|gzip|zip)$/i', '', $name);
        $name = (string) preg_replace('/[^\w.!@+=-]/u', '_', $name);
        if ('' === $name || '.' === $name || '..' === $name) {
            $name = 'report';
        }
        if (!str_ends_with(strtolower($name), '.xml')) {
            $name .= '.xml';
        }

        return mb_substr($name, -self::MAX_FIELD_LENGTH);
    }

    private static function field(string $value): string
    {
        return mb_substr(trim($value), 0, self::MAX_FIELD_LENGTH);
    }
}
