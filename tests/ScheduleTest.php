<?php

declare(strict_types=1);

namespace App\Tests;

use App\Schedule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[CoversClass(Schedule::class)]
final class ScheduleTest extends TestCase
{
    public function testGetScheduleReturnsSymfonySchedule(): void
    {
        $schedule = new Schedule($this->createStub(CacheInterface::class));

        self::assertInstanceOf(SymfonySchedule::class, $schedule->getSchedule());
    }
}
