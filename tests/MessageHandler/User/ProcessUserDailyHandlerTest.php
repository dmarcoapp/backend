<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler\User;

use App\Entity\User\User;
use App\Message\User\ProcessUserDaily;
use App\MessageHandler\User\ProcessUserDailyHandler;
use App\Repository\DMARC\ReportRepository;
use App\Repository\User\UserRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ProcessUserDailyHandler::class)]
final class ProcessUserDailyHandlerTest extends TestCase
{
    public function testHandlerReturnsEarlyWhenUserMissing(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('find')->with('missing-user-id')->willReturn(null);

        $reportRepository = $this->createMock(ReportRepository::class);
        $reportRepository->expects(self::never())->method('deleteReportsForUserOlderThan');

        $handler = new ProcessUserDailyHandler($userRepository, $reportRepository, 30);
        $handler(new ProcessUserDaily('missing-user-id'));

        self::addToAssertionCount(1);
    }

    public function testHandlerReturnsEarlyWhenReportRetentionIsDisabled(): void
    {
        $user = (new User())
            ->setEmail('handler@example.com')
            ->setName('Handler User')
            ->setSharedPostboxIdentifierToken('handler-token')
        ;

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('find')->with('existing-user-id')->willReturn($user);

        $reportRepository = $this->createMock(ReportRepository::class);
        $reportRepository->expects(self::never())->method('deleteReportsForUserOlderThan');

        $handler = new ProcessUserDailyHandler($userRepository, $reportRepository, 0);
        $handler(new ProcessUserDaily('existing-user-id'));

        self::addToAssertionCount(1);
    }

    public function testHandlerDeletesReportsOlderThanConfiguredRetention(): void
    {
        $user = (new User())
            ->setEmail('handler-retention@example.com')
            ->setName('Handler Retention User')
            ->setSharedPostboxIdentifierToken('handler-retention-token')
        ;

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('find')->with('retention-user-id')->willReturn($user);

        $reportRepository = $this->createMock(ReportRepository::class);
        $reportRepository
            ->expects(self::once())
            ->method('deleteReportsForUserOlderThan')
            ->with(
                $user,
                self::callback(static function (\DateTimeImmutable $threshold): bool {
                    $expected = new \DateTimeImmutable('-30 days', new \DateTimeZone('UTC'));

                    return abs($threshold->getTimestamp() - $expected->getTimestamp()) <= 1;
                }),
            )
        ;

        $handler = new ProcessUserDailyHandler($userRepository, $reportRepository, 30);
        $handler(new ProcessUserDaily('retention-user-id'));
    }
}
