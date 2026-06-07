<?php

declare(strict_types=1);

namespace App\Tests\Service\User;

use App\Entity\User\User;
use App\Service\User\LoginNotificationMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * @internal
 */
#[CoversClass(LoginNotificationMailer::class)]
final class LoginNotificationMailerTest extends TestCase
{
    public function testSendUsesMailerWhenEnabled(): void
    {
        $user = new User();
        $user
            ->setName('Login User')
            ->setEmail('login@example.com')
            ->setSharedPostboxIdentifierToken('token')
        ;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::isInstanceOf(RawMessage::class))
        ;

        $service = new LoginNotificationMailer($mailer);
        $service->send(
            $user,
            'HU',
            '203.0.113.10',
            'TestAgent',
            new \DateTimeImmutable('2025-01-01 00:00:00', new \DateTimeZone('UTC')),
        );
    }

    public function testSendDoesNothingWhenNotificationDisabled(): void
    {
        $user = new User();
        $user
            ->setName('Login User')
            ->setEmail('login@example.com')
            ->setSharedPostboxIdentifierToken('token')
            ->setUnusualNewLoginNotificationEnabled(false)
        ;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $service = new LoginNotificationMailer($mailer);
        $service->send(
            $user,
            'HU',
            '203.0.113.10',
            'TestAgent',
            new \DateTimeImmutable('2025-01-01 00:00:00', new \DateTimeZone('UTC')),
        );
    }

    public function testSendDoesNothingWhenEmailIsMissing(): void
    {
        $user = new User();
        $user
            ->setName('Login User')
            ->setSharedPostboxIdentifierToken('token')
        ;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $service = new LoginNotificationMailer($mailer);
        $service->send(
            $user,
            'HU',
            '203.0.113.10',
            'TestAgent',
            new \DateTimeImmutable('2025-01-01 00:00:00', new \DateTimeZone('UTC')),
        );
    }
}
