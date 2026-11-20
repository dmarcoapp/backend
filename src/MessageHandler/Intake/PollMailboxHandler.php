<?php

declare(strict_types=1);

namespace App\MessageHandler\Intake;

use App\Entity\Intake\Mailbox;
use App\Message\Intake\PollMailbox;
use App\Repository\Intake\MailboxRepository;
use App\Service\Intake\MailboxPoller;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class PollMailboxHandler
{
    public function __construct(
        private MailboxRepository $mailboxes,
        private MailboxPoller $poller,
        private MessageBusInterface $messageBus,
    ) {}

    public function __invoke(PollMailbox $message): void
    {
        $mailbox = $this->mailboxes->find($message->mailboxId);
        if (!$mailbox instanceof Mailbox || !$mailbox->isEnabled()) {
            return;
        }

        // A failure is recorded on the mailbox and retried on schedule, not by
        // the queue, which would hammer a server refusing the password.
        $result = $this->poller->poll($mailbox);

        // A backlog is read on, one run at a time.
        if ($result->more && $result->succeeded()) {
            $this->messageBus->dispatch(new PollMailbox($message->mailboxId));
        }
    }
}
