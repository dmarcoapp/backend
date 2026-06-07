<?php

declare(strict_types=1);

namespace App\Tests\Message\User;

use App\Message\User\ProcessUserWeekly;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ProcessUserWeeklyTest extends TestCase
{
    public function testCreatesMessageWithUserId(): void
    {
        $message = new ProcessUserWeekly('user-id');

        self::assertSame('user-id', $message->userId);
    }
}
