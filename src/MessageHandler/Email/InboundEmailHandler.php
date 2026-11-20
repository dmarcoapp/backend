<?php

declare(strict_types=1);

namespace App\MessageHandler\Email;

use App\Enum\Email\ReportSource;
use App\Message\Email\InboundEmail;
use App\Service\Email\AggregateReportPostboxResolver;
use App\Service\Email\ReportAttachmentDecoder;
use App\Service\Intake\IncomingReport;
use App\Service\Intake\ReportIngestor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class InboundEmailHandler
{
    public function __construct(
        private AggregateReportPostboxResolver $postboxResolver,
        private ReportIngestor $ingestor,
        private ReportAttachmentDecoder $attachmentDecoder,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws \Throwable
     */
    public function __invoke(InboundEmail $message): void
    {
        $toAddress = $message->email->to[0] ?? null;
        $messageId = $message->email->message_id;
        if (!is_string($toAddress) || '' === trim($toAddress) || !is_string($messageId) || '' === trim($messageId)) {
            $this->logger->error('Inbound email payload is invalid.');

            return;
        }

        // The webhook already rejected an unknown recipient, so this only
        // catches a report address that changed in between.
        if (!$this->postboxResolver->isReportAddress($toAddress)) {
            $this->logger->error('Inbound email is not addressed to the report address.', [
                'messageId' => $messageId,
                'toAddress' => $toAddress,
            ]);

            return;
        }

        $from = $message->email->from;
        if (!is_string($from) || '' === trim($from)) {
            $this->logger->error('Inbound email sender is missing.', [
                'messageId' => $messageId,
            ]);

            return;
        }

        $possibleDmarcAttachment = $message->email->attachments[0] ?? null;
        if (!$possibleDmarcAttachment) {
            $this->logger->error('No attachment found for email.', [
                'messageId' => $message->email->message_id,
                'toAddress' => $toAddress,
            ]);

            return;
        }
        $attachmentContent = $possibleDmarcAttachment['content'] ?? null;
        if (!is_string($attachmentContent)) {
            // Queued before the upgrade, when reports went to object storage
            // and the message only pointed at them. Failing keeps it in the
            // failed transport instead of acknowledging it away.
            throw new UnrecoverableMessageHandlingException('Inbound email carries no report content.');
        }
        $attachmentFilename = $possibleDmarcAttachment['filename'] ?? null;
        if (!is_string($attachmentFilename)) {
            $this->logger->error('Inbound email attachment payload is invalid.', [
                'messageId' => $messageId,
            ]);

            return;
        }

        try {
            $xmlContent = $this->attachmentDecoder->decode($attachmentContent);
        } catch (\InvalidArgumentException $e) {
            $this->logger->error('Inbound email attachment could not be decoded.', [
                'messageId' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $createdAt = $message->email->created_at;
        if (!$createdAt instanceof \DateTimeImmutable) {
            $this->logger->error('Inbound email created_at is missing.', [
                'messageId' => $messageId,
            ]);

            return;
        }

        $this->ingestor->ingest(new IncomingReport(
            source: ReportSource::GATEWAY,
            messageId: $messageId,
            fromAddress: $from,
            toAddress: $toAddress,
            receivedAt: $createdAt,
            filename: $attachmentFilename,
            xml: $xmlContent,
        ));
    }
}
