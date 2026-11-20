<?php

declare(strict_types=1);

namespace App\Service\Intake;

use App\Entity\Intake\Mailbox;
use App\Enum\Intake\MailboxAuthMethod;
use App\Enum\Intake\MailboxSecurity;
use DirectoryTree\ImapEngine\Mailbox as ImapMailbox;

/**
 * Opens a signed-in IMAP connection to a mailbox. The certificate is always
 * checked, and STARTTLS that the server does not take up ends the attempt
 * before the password is sent.
 */
final readonly class MailboxConnector
{
    public const int TIMEOUT_SECONDS = 30;

    public function __construct(
        private MailboxCredentials $credentials,
    ) {}

    public function connect(Mailbox $mailbox): ImapMailbox
    {
        $client = new ImapMailbox([
            'authentication' => match ($mailbox->getAuthMethod()) {
                MailboxAuthMethod::PASSWORD => 'plain',
            },
            'debug' => false,
            'encryption' => match ($mailbox->getSecurity()) {
                MailboxSecurity::TLS => 'ssl',
                MailboxSecurity::STARTTLS => 'starttls',
            },
            'host' => $mailbox->getHost(),
            'password' => $this->credentials->password($mailbox),
            'port' => $mailbox->getPort(),
            'timeout' => self::TIMEOUT_SECONDS,
            'username' => $mailbox->getUsername(),
            'validate_cert' => true,
        ]);
        $client->connect();

        return $client;
    }
}
