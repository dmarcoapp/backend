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
    public function testCreateHashesThePasswordAndSaves(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);

        $savedUser = null;
        $userRepository->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (object $user) use (&$savedUser): void {
                $savedUser = $user;
            });

        $passwordHasher->method('hashPassword')->willReturn('hashed-password');

        $factory = new UserFactory($userRepository, $passwordHasher);

        $user = $factory->create(
            email: 'account@example.com',
            name: 'Account',
            plainPassword: 'Str0ngPassw0rd!@#',
        );

        self::assertSame('hashed-password', $user->getPassword());
        self::assertSame($user, $savedUser);
    }
}
