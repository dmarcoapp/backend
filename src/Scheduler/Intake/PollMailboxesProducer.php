<?php

declare(strict_types=1);

namespace App\Scheduler\Intake;

use App\Message\Intake\PollMailbox;
use App\Repository\Intake\MailboxRepository;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

/**
 * Looks every few minutes for the mailboxes due another read. How often each
 * is read is its own setting, so changing it needs no restart.
 */
#[AsPeriodicTask(frequency: '5 minutes', jitter: 30)]
final readonly class PollMailboxesProducer
{
    public function __construct(
        private MailboxRepository $mailboxes,
        private MessageBusInterface $messageBus,
    ) {}

    public function __invoke(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        foreach ($this->mailboxes->findEnabled() as $mailbox) {
            if ($mailbox->isDue($now)) {
                $this->messageBus->dispatch(new PollMailbox($mailbox->getId()->toRfc4122()));
            }
        }
    }
}
