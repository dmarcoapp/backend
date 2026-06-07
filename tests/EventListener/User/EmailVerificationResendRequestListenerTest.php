<?php

declare(strict_types=1);

namespace App\Tests\EventListener\User;

use App\Entity\User\User;
use App\Event\User\EmailVerificationResendEvent;
use App\EventListener\User\EmailVerificationResendListener;
use App\Service\User\VerificationMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * @internal
 */
#[CoversClass(EmailVerificationResendListener::class)]
final class EmailVerificationResendRequestListenerTest extends TestCase
{
    public function testInvokeSkipsWhenTokenMissing(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setEmailVerificationToken(null);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $listener = new EmailVerificationResendListener(new VerificationMailer($mailer, 3600));

        $listener(new EmailVerificationResendEvent($user));
    }

    public function testInvokeSkipsWhenTokenExpired(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setEmailVerificationToken('token');
        $user->setEmailVerificationTokenExpiresAt(new \DateTimeImmutable('-1 minute'));

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $listener = new EmailVerificationResendListener(new VerificationMailer($mailer, 3600));

        $listener(new EmailVerificationResendEvent($user));
    }

    public function testInvokeSendsWhenTokenValid(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setName('User');
        $user->setEmailVerificationToken('token');
        $user->setEmailVerificationTokenExpiresAt(new \DateTimeImmutable('+1 hour'));

        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::isInstanceOf(RawMessage::class))
        ;

        $listener = new EmailVerificationResendListener(new VerificationMailer($mailer, 3600));

        $listener(new EmailVerificationResendEvent($user));
    }

    public function testInvokeSkipsWhenTokenMissingExpiration(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setEmailVerificationToken('token');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $listener = new EmailVerificationResendListener(new VerificationMailer($mailer, 3600));

        $listener(new EmailVerificationResendEvent($user));
    }
}
