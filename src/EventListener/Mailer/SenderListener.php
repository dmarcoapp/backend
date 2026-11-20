<?php

declare(strict_types=1);

namespace App\EventListener\Mailer;

use App\Service\Platform\PlatformSettingsProvider;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends every email from the sender address of the platform settings, which
 * the mailer configuration cannot know about because it is fixed at build time.
 */
#[AsEventListener(event: MessageEvent::class)]
final readonly class SenderListener
{
    public function __construct(
        private PlatformSettingsProvider $settings,
    ) {}

    public function __invoke(MessageEvent $event): void
    {
        $message = $event->getMessage();
        if (!$message instanceof Email) {
            return;
        }

        $sender = $this->settings->senderAddress();

        if ([] === $message->getFrom()) {
            $message->from(new Address($sender, 'DMARCo'));
        }

        $event->getEnvelope()->setSender(new Address($sender));
    }
}
