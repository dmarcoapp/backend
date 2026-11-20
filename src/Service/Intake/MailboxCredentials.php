<?php

declare(strict_types=1);

namespace App\Service\Intake;

use App\Entity\Intake\Mailbox;
use App\Enum\Intake\MailboxAuthMethod;
use App\Service\Security\SecretBox;
use App\Service\Security\UnreadableSecretException;

/**
 * Keeps what signing in to a mailbox takes, encrypted for that mailbox alone.
 * It is JSON, so the tokens OAuth will need fit in beside the password.
 */
final readonly class MailboxCredentials
{
    public function __construct(
        private SecretBox $secretBox,
    ) {}

    public function setPassword(Mailbox $mailbox, #[\SensitiveParameter] string $password): void
    {
        $mailbox->setEncryptedCredentials(
            MailboxAuthMethod::PASSWORD,
            $this->secretBox->encrypt(json_encode(['password' => $password], JSON_THROW_ON_ERROR), $mailbox->credentialsContext()),
        );
    }

    /**
     * @throws UnreadableSecretException
     */
    public function password(Mailbox $mailbox): string
    {
        $credentials = json_decode(
            $this->secretBox->decrypt($mailbox->getEncryptedCredentials(), $mailbox->credentialsContext()),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $password = is_array($credentials) ? ($credentials['password'] ?? null) : null;
        if (!is_string($password)) {
            throw new UnreadableSecretException('The stored credentials hold no password.');
        }

        return $password;
    }

    public function isReadable(Mailbox $mailbox): bool
    {
        try {
            $this->password($mailbox);

            return true;
        } catch (\JsonException|UnreadableSecretException) {
            return false;
        }
    }
}
