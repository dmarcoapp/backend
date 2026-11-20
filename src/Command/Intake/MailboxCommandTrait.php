<?php

declare(strict_types=1);

namespace App\Command\Intake;

use App\Entity\Intake\Mailbox;
use App\Repository\Intake\MailboxRepository;
use Symfony\Component\Uid\Uuid;

trait MailboxCommandTrait
{
    /**
     * The mailbox an argument names, by ID or by name.
     */
    private function findMailbox(MailboxRepository $mailboxes, string $idOrName): ?Mailbox
    {
        if (Uuid::isValid($idOrName)) {
            $mailbox = $mailboxes->find($idOrName);
            if ($mailbox instanceof Mailbox) {
                return $mailbox;
            }
        }

        return $mailboxes->findOneBy(['name' => $idOrName]);
    }
}
