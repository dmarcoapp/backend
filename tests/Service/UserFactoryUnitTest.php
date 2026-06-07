<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Event\User\UserCreatedEvent;
use App\Repository\User\UserRepository;
use App\Service\User\TwoFactor\TwoFactorSecretGenerator;
use App\Service\User\UserFactory;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @internal
 *
 * @coversNothing
 */
final class UserFactoryUnitTest extends TestCase
{
    public function testCreateRegeneratesTokensOnCollision(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $twoFactorSecretGenerator = new TwoFactorSecretGenerator();

        $userRepository->method('findOneBy')->willReturnOnConsecutiveCalls(
            new \stdClass(),
            null,
            new \stdClass(),
            null,
        );

        $savedUser = null;
        $userRepository->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (object $user) use (&$savedUser): void {
                $savedUser = $user;
            });

        $passwordHasher->method('hashPassword')->willReturn('hashed-password');

        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(UserCreatedEvent::class))
        ;

        $factory = new UserFactory(
            $userRepository,
            $passwordHasher,
            $eventDispatcher,
            $twoFactorSecretGenerator,
            3600,
            8,
        );

        $user = $factory->create(
            email: 'collision@example.com',
            name: 'Collision User',
            plainPassword: 'Str0ngPassw0rd!@#',
            roles: ['ROLE_USER'],
        );

        self::assertSame('hashed-password', $user->getPassword());
        self::assertNotNull($user->getEmailVerificationToken());
        self::assertNotNull($user->getSharedPostboxIdentifierToken());
        self::assertSame($user, $savedUser);
    }
}
