<?php

declare(strict_types=1);

namespace App\Tests;

use App\Schedule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Scheduler\Generator\MessageGenerator;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
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

    /**
     * Each run below is a separate worker process sharing the stored
     * checkpoint, as after a restart. A task missed while the worker was down
     * runs once, for its latest missed date, and a restart afterwards must not
     * run it again. Before symfony/scheduler 7.4.21 the checkpoint kept the
     * earliest missed date instead, so every restart replayed the weekly and
     * daily tasks.
     */
    public function testRestartDoesNotReplayTasksThatAlreadyRan(): void
    {
        $cache = new ArrayAdapter();

        self::assertSame([], self::runWorker($cache, '2026-07-01 12:00'));

        self::assertSame(
            ['weekly 2026-10-19 00:01', 'daily 2026-10-21 00:00'],
            self::runWorker($cache, '2026-10-21 18:00'),
        );
        self::assertSame([], self::runWorker($cache, '2026-10-21 18:02'));
        self::assertSame([], self::runWorker($cache, '2026-10-21 19:30'));

        self::assertSame(
            ['daily 2026-10-26 00:00', 'weekly 2026-10-26 00:01'],
            self::runWorker($cache, '2026-10-26 00:05'),
        );
    }

    /**
     * @return list<string> the tasks that ran, with the date they ran for
     */
    private static function runWorker(CacheInterface $cache, string $now): array
    {
        $provider = new class($cache) implements ScheduleProviderInterface {
            public function __construct(
                private readonly CacheInterface $cache,
            ) {}

            #[\Override]
            public function getSchedule(): SymfonySchedule
            {
                return new Schedule($this->cache)->getSchedule()->add(
                    RecurringMessage::cron('0 0 * * *', (object) ['task' => 'daily'], 'UTC'),
                    RecurringMessage::cron('1 0 * * 1', (object) ['task' => 'weekly'], 'UTC'),
                );
            }
        };

        $generator = new MessageGenerator($provider, 'default', new MockClock($now.' UTC'));

        $runs = [];
        foreach ($generator->getMessages() as $context => $message) {
            \assert(isset($message->task) && \is_string($message->task));
            $runs[] = $message->task.' '.$context->triggeredAt->format('Y-m-d H:i');
        }

        return $runs;
    }
}
