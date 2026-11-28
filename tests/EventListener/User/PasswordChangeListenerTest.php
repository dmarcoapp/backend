<?php

declare(strict_types=1);

namespace App\Tests\EventListener\User;

use App\Entity\User\User;
use App\Event\User\PasswordChangeEvent;
use App\EventListener\User\PasswordChangeListener;
use App\Service\User\PasswordChangedMailer;
use App\Service\User\SessionRevoker;
use App\Entity\RefreshToken;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
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
        ;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::isInstanceOf(RawMessage::class))
        ;

        $listener = new PasswordChangeListener(new PasswordChangedMailer($mailer), $this->sessionRevoker($user));
        $listener(new PasswordChangeEvent($user));
    }

    public function testInvokeDelegatesWhenEmailMissing(): void
    {
        $user = new User();
        $user
            ->setName('User')
        ;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $listener = new PasswordChangeListener(new PasswordChangedMailer($mailer), $this->sessionRevoker());
        $listener(new PasswordChangeEvent($user));
    }

    private function sessionRevoker(?User $expectedUser = null): SessionRevoker
    {
        if (null === $expectedUser) {
            $connection = $this->createStub(Connection::class);
        } else {
            $connection = $this->createMock(Connection::class);
            $connection
                ->expects(self::once())
                ->method('delete')
                ->with('refresh_tokens', ['username' => $expectedUser->getUserIdentifier()])
                ->willReturn(1)
            ;
        }
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getTableName')->willReturn('refresh_tokens');
        $metadata->method('getColumnName')->willReturn('username');
        $entityManager->method('getClassMetadata')->willReturn($metadata);

        return new SessionRevoker(
            $entityManager,
            $this->createStub(JWTTokenManagerInterface::class),
            $this->createStub(RefreshTokenGeneratorInterface::class),
            $this->createStub(RefreshTokenManagerInterface::class),
            2592000,
        );
    }
}
