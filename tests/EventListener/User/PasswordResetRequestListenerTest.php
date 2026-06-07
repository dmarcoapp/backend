<?php

declare(strict_types=1);

namespace App\Tests\EventListener\User;

use App\Entity\User\User;
use App\Event\User\PasswordResetEvent;
use App\EventListener\User\PasswordResetListener;
use App\Repository\User\UserRepository;
use App\Service\User\PasswordResetMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * @internal
 */
#[CoversClass(PasswordResetListener::class)]
final class PasswordResetRequestListenerTest extends TestCase
{
    public function testGenerateAndSavePasswordResetTokenSkipsWhenRecent(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setPasswordResetToken('token');
        $user->setPasswordResetTokenExpiresAt(new \DateTimeImmutable('+3600 seconds'));

        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::never())->method('save');

        $listener = new PasswordResetListener(
            $repository,
            new PasswordResetMailer($this->createStub(MailerInterface::class), 3600),
            3600,
            8
        );

        $listener->generateAndSavePasswordResetToken($user);

        self::assertSame('token', $user->getPasswordResetToken());
    }

    public function testInvokeSavesTokenAndDelegatesToMailer(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setName('User');

        $repository = $this->createMock(UserRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $repository->expects(self::once())->method('save')->with($user);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::isInstanceOf(RawMessage::class))
        ;

        $listener = new PasswordResetListener(
            $repository,
            new PasswordResetMailer($mailer, 3600),
            3600,
            8
        );

        $listener(new PasswordResetEvent($user));

        self::assertNotNull($user->getPasswordResetToken());
    }

    public function testInvokeStillDelegatesToMailerWhenEmailMissing(): void
    {
        $user = new User();
        $user->setName('User');

        $repository = $this->createMock(UserRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $repository->expects(self::once())->method('save')->with($user);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $listener = new PasswordResetListener(
            $repository,
            new PasswordResetMailer($mailer, 3600),
            3600,
            8
        );

        $listener(new PasswordResetEvent($user));
    }
}
