<?php

declare(strict_types=1);

namespace App\Service\Mailer;

use App\Service\Platform\PlatformSettingsProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Makes platform://default, the DSN the mailer is configured with, mean the
 * mail server of the platform settings.
 */
#[AutoconfigureTag('mailer.transport_factory')]
final readonly class PlatformTransportFactory implements TransportFactoryInterface
{
    public function __construct(
        private PlatformSettingsProvider $settings,
        private EventDispatcherInterface $dispatcher,
        private LoggerInterface $logger,
    ) {}

    #[\Override]
    public function create(Dsn $dsn): TransportInterface
    {
        if (!$this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn, 'platform', ['platform']);
        }

        return new PlatformTransport($this->settings, $this->dispatcher, $this->logger);
    }

    #[\Override]
    public function supports(Dsn $dsn): bool
    {
        return 'platform' === $dsn->getScheme();
    }
}
