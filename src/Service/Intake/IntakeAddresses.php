<?php

declare(strict_types=1);

namespace App\Service\Intake;

use App\Repository\Intake\MailboxRepository;
use App\Service\Email\AggregateReportPostboxAddressProvider;
use App\Service\Platform\PlatformSettingsProvider;

/**
 * The addresses a DMARC record can send reports to: the webhook's, while
 * the API takes reports, and the report address of each enabled mailbox.
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

        $webhook = $this->webhookAddress->getAddress();
        if (null !== $webhook && $this->settings->isWebhookEnabled()) {
            $addresses[] = new IntakeAddress($webhook, IntakeAddress::WEBHOOK, 'Webhook');
        }

        foreach ($this->mailboxes->findEnabled() as $mailbox) {
            $address = $mailbox->getReportAddress();
            if (null !== $address && '' !== $address) {
                $addresses[] = new IntakeAddress($address, IntakeAddress::MAILBOX, $mailbox->getName());
            }
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
