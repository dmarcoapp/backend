<?php

declare(strict_types=1);

namespace App\Tests\EventListener\Mailer;

use App\Entity\Platform\PlatformSettings;
use App\EventListener\Mailer\SenderListener;
use App\Repository\Platform\PlatformSettingsRepository;
use App\Service\Platform\LegacyEnvironment;
use App\Service\Platform\PlatformSettingsProvider;
use App\Service\Security\SecretBox;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * @internal
 */
#[CoversClass(SenderListener::class)]
final class SenderListenerTest extends TestCase
{
    public function testSendsFromTheSenderAddressOfTheSettings(): void
    {
        $email = new Email()->to('owner@example.com')->text('Hello');
        $event = new MessageEvent($email, new Envelope(new Address('placeholder@example.com'), [new Address('owner@example.com')]), 'platform://default');

        $this->listener('alerts@example.org')($event);

        self::assertSame('alerts@example.org', $email->getFrom()[0]->getAddress());
        self::assertSame('DMARCo', $email->getFrom()[0]->getName());
        self::assertSame('alerts@example.org', $event->getEnvelope()->getSender()->getAddress());
    }

    public function testKeepsASenderTheMessageAlreadyHas(): void
    {
        $email = new Email()->from('someone@example.net')->to('owner@example.com')->text('Hello');
        $event = new MessageEvent($email, new Envelope(new Address('someone@example.net'), [new Address('owner@example.com')]), 'platform://default');

        $this->listener('alerts@example.org')($event);

        self::assertSame('someone@example.net', $email->getFrom()[0]->getAddress());
        self::assertSame('alerts@example.org', $event->getEnvelope()->getSender()->getAddress());
    }

    public function testLeavesAMessageThatIsNoEmailAlone(): void
    {
        $message = new RawMessage('raw');
        $envelope = new Envelope(new Address('placeholder@example.com'), [new Address('owner@example.com')]);

        $this->listener('alerts@example.org')(new MessageEvent($message, $envelope, 'platform://default'));

        self::assertSame('placeholder@example.com', $envelope->getSender()->getAddress());
    }

    private function listener(string $senderAddress): SenderListener
    {
        $repository = $this->createStub(PlatformSettingsRepository::class);
        $repository->method('get')->willReturn(new PlatformSettings('token')->setSenderAddress($senderAddress));

        return new SenderListener(new PlatformSettingsProvider(
            $repository,
            new SecretBox(str_repeat('ab', 32)),
            new LegacyEnvironment([]),
            'https://dmarc.example.test',
        ));
    }
}
