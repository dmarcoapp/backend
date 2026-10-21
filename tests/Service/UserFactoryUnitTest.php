<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\User\UserRepository;
use App\Service\User\UserFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @internal
 *
 * @coversNothing
 */
final class UserFactoryUnitTest extends TestCase
{
    public function testCreateRegeneratesThePostboxTokenOnCollision(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);

        $userRepository->expects(self::exactly(2))
            ->method('findOneBy')
            ->with(self::arrayHasKey('sharedPostboxIdentifierToken'))
            ->willReturnOnConsecutiveCalls(new \stdClass(), null)
        ;

        $savedUser = null;
        $userRepository->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (object $user) use (&$savedUser): void {
                $savedUser = $user;
            });

        $passwordHasher->method('hashPassword')->willReturn('hashed-password');

        $factory = new UserFactory($userRepository, $passwordHasher);

        $user = $factory->create(
            email: 'collision@example.com',
            name: 'Collision User',
            plainPassword: 'Str0ngPassw0rd!@#',
        );

        self::assertSame('hashed-password', $user->getPassword());
        self::assertNotNull($user->getSharedPostboxIdentifierToken());
        self::assertSame($user, $savedUser);
    }
}
