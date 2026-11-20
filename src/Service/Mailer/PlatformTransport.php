<?php

declare(strict_types=1);

namespace App\Service\Mailer;

use App\Service\Platform\PlatformSettingsProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Sends through the mail server the platform settings name at the moment of
 * sending, so a change on the Platform page applies without a restart, the
 * long-running worker included.
 */
final class PlatformTransport implements \Stringable, TransportInterface
{
    private ?string $builtFor = null;
    private ?TransportInterface $transport = null;

    public function __construct(
        private readonly PlatformSettingsProvider $settings,
        private readonly ?EventDispatcherInterface $dispatcher = null,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    #[\Override]
    public function __toString(): string
    {
        return 'platform://default';
    }

    #[\Override]
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        return $this->transport()->send($message, $envelope);
    }

    private function transport(): TransportInterface
    {
        $dsn = $this->settings->mailerDsn();
        $fingerprint = hash('sha256', serialize([
            $dsn->getScheme(),
            $dsn->getHost(),
            $dsn->getPort(),
            $dsn->getUser(),
            $dsn->getPassword(),
            $dsn->getOption('auto_tls'),
            $dsn->getOption('require_tls'),
        ]));

        if (null === $this->transport || $fingerprint !== $this->builtFor) {
            // The dispatcher is what runs the mailer's listeners, the sender one
            // among them, when the message goes out. The Symfony plugin's stub
            // of the contracts interface leaves out that it extends the PSR one.
            /** @psalm-suppress InvalidArgument */
            $factories = Transport::getDefaultFactories($this->dispatcher, null, $this->logger);
            $this->transport = new Transport(iterator_to_array($factories))->fromDsnObject($dsn);
            $this->builtFor = $fingerprint;
        }

        return $this->transport;
    }
}
