<?php

declare(strict_types=1);

namespace App\MessageHandler\Email;

use App\Entity\Email\Email;
use App\Message\Email\InboundEmail;
use App\Message\Email\ProcessEmail;
use App\Repository\Email\EmailRepository;
use App\Repository\User\UserRepository;
use App\Service\User\BlocklistMatcher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\String\ByteString;

#[AsMessageHandler]
final readonly class InboundEmailHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private EmailRepository $emailRepository,
        private MessageBusInterface $messageBus,
        private BlocklistMatcher $blocklistMatcher,
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

        $email = $this->emailRepository->findOneBy([
            'messageId' => $messageId,
            'toAddress' => $toAddress,
        ]);

        if ($email) {
            $this->logger->error('Inbound email already handled.', [
                'messageId' => $messageId,
                'toAddress' => $toAddress,
            ]);

            return;
        }

        $postboxIdentifier = new ByteString($toAddress)->before('@')->toString();
        $user = $this->userRepository->findOneBy(['sharedPostboxIdentifierToken' => $postboxIdentifier]);

        if (!$user) {
            $this->logger->error('User not found for email.', [
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
        $fromAddress = $this->normalizeFromAddress($from);

        if ($this->blocklistMatcher->isBlocked($user, $fromAddress)) {
            $this->logger->info('Inbound email blocked by user blocklist.', [
                'messageId' => $message->email->message_id,
                'fromAddress' => $fromAddress,
                'userId' => $user->getId()?->toRfc4122(),
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
        $attachmentBucket = $possibleDmarcAttachment['bucket'] ?? null;
        $attachmentKey = $possibleDmarcAttachment['key'] ?? null;
        $attachmentFilename = $possibleDmarcAttachment['filename'] ?? null;
        $attachmentContentType = $possibleDmarcAttachment['content_type'] ?? null;
        if (
            !is_string($attachmentBucket)
            || !is_string($attachmentKey)
            || !is_string($attachmentFilename)
            || !is_string($attachmentContentType)
        ) {
            $this->logger->error('Inbound email attachment payload is invalid.', [
                'messageId' => $messageId,
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

        $email = new Email()
            ->setCreatedAt($createdAt)
            ->setFromAddress($fromAddress)
            ->setToAddress($toAddress)
            ->setMessageId($messageId)
            ->setAttachmentBucket($attachmentBucket)
            ->setAttachmentKey($attachmentKey)
            ->setAttachmentFilename($attachmentFilename)
            ->setAttachmentContentType($attachmentContentType)
            ->setOwner($user)
        ;

        $this->emailRepository->save($email);
        $emailId = $email->getId();
        if (null === $emailId) {
            throw new \LogicException('Persisted email ID is missing.');
        }

        $this->messageBus->dispatch(
            new ProcessEmail(emailId: $emailId->toRfc4122()),
        );
    }

    private function normalizeFromAddress(string $fromAddress): string
    {
        try {
            return Address::create($fromAddress)->getAddress();
        } catch (\InvalidArgumentException) {
            return trim($fromAddress);
        }
    }
}
