<?php

declare(strict_types=1);

namespace App\Service\Intake;

use App\DTO\Input\Intake\MailboxInput;
use App\Entity\Intake\Mailbox;
use App\Enum\Intake\AfterImport;
use App\Enum\Intake\MailboxSecurity;

/**
 * Turns validated input into a mailbox, for the API and the console alike.
 */
final readonly class MailboxEditor
{
    public function __construct(
        private MailboxCredentials $credentials,
    ) {}

    /**
     * @throws \InvalidArgumentException when the input misses what a new mailbox needs
     */
    public function create(MailboxInput $input): Mailbox
    {
        if (null === $input->name || null === $input->host || null === $input->username || null === $input->password || '' === $input->password) {
            throw new \InvalidArgumentException('A new mailbox needs a name, a server, a username and a password.');
        }

        $mailbox = new Mailbox(
            trim($input->name),
            strtolower(trim($input->host)),
            MailboxSecurity::from($input->security ?? MailboxSecurity::TLS->value),
            trim($input->username),
        );
        $this->update($mailbox, $input);

        return $mailbox;
    }

    /**
     * @throws \InvalidArgumentException when the change leaves the mailbox inconsistent
     */
    public function update(Mailbox $mailbox, MailboxInput $input): void
    {
        $readFrom = [$mailbox->getHost(), $mailbox->getUsername(), $mailbox->getFolder()];

        if (null !== $input->name) {
            $mailbox->setName(trim($input->name));
        }
        if (null !== $input->host) {
            $mailbox->setHost(strtolower(trim($input->host)));
        }
        if (null !== $input->security) {
            $security = MailboxSecurity::from($input->security);
            // A port nobody chose follows the kind of security.
            if (null === $input->port && $mailbox->getPort() === $mailbox->getSecurity()->defaultPort()) {
                $mailbox->setPort($security->defaultPort());
            }
            $mailbox->setSecurity($security);
        }
        if (null !== $input->port) {
            $mailbox->setPort($input->port);
        }
        if (null !== $input->username) {
            $mailbox->setUsername(trim($input->username));
        }
        if (null !== $input->password && '' !== $input->password) {
            $this->credentials->setPassword($mailbox, $input->password);
        }
        if (null !== $input->folder) {
            $mailbox->setFolder(trim($input->folder));
        }
        if (null !== $input->reportAddress) {
            $address = strtolower(trim($input->reportAddress));
            $mailbox->setReportAddress('' === $address ? null : $address);
        }
        if (null !== $input->afterImport) {
            $mailbox->setAfterImport(AfterImport::from($input->afterImport), null === $input->moveToFolder ? null : trim($input->moveToFolder));
        } elseif (null !== $input->moveToFolder && AfterImport::MOVE === $mailbox->getAfterImport()) {
            $mailbox->setAfterImport(AfterImport::MOVE, trim($input->moveToFolder));
        }
        if (null !== $input->enabled) {
            $mailbox->setEnabled($input->enabled);
        }
        if (null !== $input->pollIntervalMinutes) {
            $mailbox->setPollIntervalMinutes($input->pollIntervalMinutes);
        }
        if (null !== $input->firstImportDays) {
            $mailbox->setFirstImportDays($input->firstImportDays);
        }

        if (AfterImport::MOVE === $mailbox->getAfterImport() && $mailbox->getMoveToFolder() === $mailbox->getFolder()) {
            throw new \InvalidArgumentException('Reports cannot be moved to the folder they are read from.');
        }

        // Another server, account or folder is another set of UIDs, so the
        // mailbox is read as if new, and stored reports are recognized.
        if ($readFrom !== [$mailbox->getHost(), $mailbox->getUsername(), $mailbox->getFolder()]) {
            $mailbox->setCursor(null, null);
        }
    }
}
