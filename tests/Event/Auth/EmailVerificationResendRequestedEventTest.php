<?php

declare(strict_types=1);

namespace App\Tests\Event\Auth;

use App\Entity\User\User;
use App\Event\User\EmailVerificationResendEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EmailVerificationResendEvent::class)]
final class EmailVerificationResendRequestedEventTest extends TestCase
{
    public function testGetUserReturnsInstance(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');

        $event = new EmailVerificationResendEvent($user);

        self::assertSame($user, $event->getUser());
    }
}
