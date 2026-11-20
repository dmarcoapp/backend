<?php

declare(strict_types=1);

namespace App\Tests\Service\Mailer;

use App\Entity\Platform\PlatformSettings;
use App\Repository\Platform\PlatformSettingsRepository;
use App\Service\Mailer\PlatformTransport;
use App\Service\Mailer\PlatformTransportFactory;
use App\Service\Platform\LegacyEnvironment;
use App\Service\Platform\PlatformSettingsProvider;
use App\Service\Security\SecretBox;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[CoversClass(PlatformTransport::class)]
#[CoversClass(PlatformTransportFactory::class)]
final class PlatformTransportTest extends TestCase
{
    public function testTheFactoryOwnsThePlatformScheme(): void
    {
        $factory = new PlatformTransportFactory($this->provider(new PlatformSettings('token')), new EventDispatcher(), new NullLogger());

        self::assertTrue($factory->supports(new Dsn('platform', 'default')));
        self::assertFalse($factory->supports(new Dsn('smtp', 'mail.example.com')));
        self::assertSame('platform://default', (string) $factory->create(new Dsn('platform', 'default')));

        $this->expectException(UnsupportedSchemeException::class);
        $factory->create(new Dsn('smtp', 'mail.example.com'));
    }

    public function testSendsThroughTheTransportTheSettingsNameAndRunsTheMailerListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $seen = [];
        $dispatcher->addListener(MessageEvent::class, static function (MessageEvent $event) use (&$seen): void {
            $seen[] = $event->getTransport();
        });

        $transport = new PlatformTransport($this->provider(new PlatformSettings('token')), $dispatcher, new NullLogger());
        $sent = $transport->send(new Email()->from('dmarco@example.com')->to('owner@example.com')->text('Hello'));

        self::assertNotNull($sent);
        self::assertSame(['null://'], $seen);
    }

    private function provider(PlatformSettings $settings): PlatformSettingsProvider
    {
        $repository = $this->createStub(PlatformSettingsRepository::class);
        $repository->method('get')->willReturn($settings);

        return new PlatformSettingsProvider($repository, new SecretBox(str_repeat('ab', 32)), new LegacyEnvironment([]), 'https://dmarc.example.test');
    }
}
