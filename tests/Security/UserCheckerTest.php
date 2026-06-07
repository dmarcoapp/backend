<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User\User;
use App\Security\UserChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * @internal
 */
#[CoversClass(UserChecker::class)]
final class UserCheckerTest extends TestCase
{
    public function testCheckPreAuthSkipsNonAppUser(): void
    {
        $checker = new UserChecker();

        $checker->checkPreAuth(new InMemoryUser('user', 'pass'));

        self::assertTrue(true);
    }

    public function testCheckPreAuthThrowsForUnverifiedUser(): void
    {
        $checker = new UserChecker();

        $user = new User();
        $user->setEmail('user@example.com');
        $user->setEmailVerificationToken('token');

        $this->expectException(CustomUserMessageAccountStatusException::class);

        $checker->checkPreAuth($user);
    }

    public function testCheckPreAuthAllowsVerifiedUser(): void
    {
        $checker = new UserChecker();

        $user = new User();
        $user->setEmail('user@example.com');
        $user->setEmailVerificationToken(null);

        $checker->checkPreAuth($user);

        self::assertTrue(true);
    }

    public function testCheckPostAuthNoops(): void
    {
        $checker = new UserChecker();

        $checker->checkPostAuth(new InMemoryUser('user', 'pass'));

        self::assertTrue(true);
    }
}
