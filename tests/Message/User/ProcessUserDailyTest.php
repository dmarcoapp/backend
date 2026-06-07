<?php

declare(strict_types=1);

namespace App\Tests\Message\User;

use App\Message\User\ProcessUserDaily;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ProcessUserDailyTest extends TestCase
{
    public function testCreatesMessageWithUserId(): void
    {
        $message = new ProcessUserDaily('user-id');

        self::assertSame('user-id', $message->userId);
    }
}
