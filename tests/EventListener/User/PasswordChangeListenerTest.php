<?php

declare(strict_types=1);

namespace App\Tests\EventListener\User;

use App\Entity\User\User;
use App\Event\User\PasswordChangeEvent;
use App\EventListener\User\PasswordChangeListener;
use App\Service\User\PasswordChangedMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * @internal
 */
#[CoversClass(PasswordChangeListener::class)]
final class PasswordChangeListenerTest extends TestCase
{
    public function testInvokeSendsEmail(): void
    {
        $user = new User();
        $user
            ->setEmail('user@example.com')
            ->setName('User')
            ->setSharedPostboxIdentifierToken('token')
        ;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::isInstanceOf(RawMessage::class))
        ;

        $listener = new PasswordChangeListener(new PasswordChangedMailer($mailer));
        $listener(new PasswordChangeEvent($user));
    }

    public function testInvokeDelegatesWhenEmailMissing(): void
    {
        $user = new User();
        $user
            ->setName('User')
            ->setSharedPostboxIdentifierToken('token')
        ;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $listener = new PasswordChangeListener(new PasswordChangedMailer($mailer));
        $listener(new PasswordChangeEvent($user));
    }
}
