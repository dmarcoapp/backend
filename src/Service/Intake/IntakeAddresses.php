<?php

declare(strict_types=1);

namespace App\Service\Intake;

use App\Repository\Intake\MailboxRepository;
use App\Service\Email\AggregateReportPostboxAddressProvider;
use App\Service\Platform\PlatformSettingsProvider;

/**
 * The addresses a DMARC record can send reports to: the report address of
 * each enabled mailbox, and then the webhook's, while the webhook is on.
 * Mailboxes come first, because they are how a new install takes reports.
 */
final readonly class IntakeAddresses
{
    public function __construct(
        private PlatformSettingsProvider $settings,
        private AggregateReportPostboxAddressProvider $webhookAddress,
        private MailboxRepository $mailboxes,
    ) {}

    /**
     * @return list<IntakeAddress>
     */
    public function all(): array
    {
        $addresses = [];

        foreach ($this->mailboxes->findEnabled() as $mailbox) {
            $address = $mailbox->getReportAddress();
            if (null !== $address && '' !== $address) {
                $addresses[] = new IntakeAddress($address, IntakeAddress::MAILBOX, $mailbox->getName());
            }
        }

        $webhook = $this->webhookAddress->getAddress();
        if (null !== $webhook && $this->settings->isWebhookEnabled()) {
            $addresses[] = new IntakeAddress($webhook, IntakeAddress::WEBHOOK, 'Webhook');
        }

        return $addresses;
    }

    /**
     * The address to suggest when only one is shown.
     */
    public function primary(): ?string
    {
        return ($this->all()[0] ?? null)?->address;
    }
}
