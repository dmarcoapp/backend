<?php

declare(strict_types=1);

namespace App\Tests\EventListener\User;

use App\Entity\User\User;
use App\Event\User\UserCreatedEvent;
use App\EventListener\User\UserCreatedListener;
use App\Service\User\VerificationMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * @internal
 */
#[CoversClass(UserCreatedListener::class)]
final class UserCreatedListenerTest extends TestCase
{
    public function testInvokeSendsVerificationEmail(): void
    {
        $user = new User();
        $user
            ->setEmail('user@example.com')
            ->setName('User')
            ->setSharedPostboxIdentifierToken('token')
            ->setEmailVerificationToken('verification-token')
            ->setEmailVerificationTokenExpiresAt(new \DateTimeImmutable('+1 hour'))
        ;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::isInstanceOf(RawMessage::class))
        ;

        $listener = new UserCreatedListener(new VerificationMailer($mailer, 3600));
        $listener(new UserCreatedEvent($user));
    }

    public function testInvokeDelegatesWhenEmailMissing(): void
    {
        $user = new User();
        $user
            ->setName('User')
            ->setSharedPostboxIdentifierToken('token')
            ->setEmailVerificationToken('verification-token')
        ;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $listener = new UserCreatedListener(new VerificationMailer($mailer, 3600));
        $listener(new UserCreatedEvent($user));
    }
}
