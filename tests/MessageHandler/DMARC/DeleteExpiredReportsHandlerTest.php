<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler\DMARC;

use App\Entity\Platform\PlatformSettings;
use App\Message\DMARC\DeleteExpiredReports;
use App\MessageHandler\DMARC\DeleteExpiredReportsHandler;
use App\Repository\DMARC\ReportRepository;
use App\Tests\Support\PlatformSettingsTestFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DeleteExpiredReportsHandler::class)]
final class DeleteExpiredReportsHandlerTest extends TestCase
{
    public function testHandlerReturnsEarlyWhenReportRetentionIsDisabled(): void
    {
        $reportRepository = $this->createMock(ReportRepository::class);
        $reportRepository->expects(self::never())->method('deleteReportsOlderThan');

        $handler = new DeleteExpiredReportsHandler($reportRepository, PlatformSettingsTestFactory::provider(new PlatformSettings('token')->setReportRetentionDays(0)));
        $handler(new DeleteExpiredReports());
    }

    public function testHandlerKeepsAYearOfReportsByDefault(): void
    {
        $reportRepository = $this->createMock(ReportRepository::class);
        $reportRepository
            ->expects(self::once())
            ->method('deleteReportsOlderThan')
            ->with(self::callback(static function (\DateTimeImmutable $threshold): bool {
                $expected = new \DateTimeImmutable('-365 days', new \DateTimeZone('UTC'));

                return abs($threshold->getTimestamp() - $expected->getTimestamp()) <= 1;
            }))
        ;

        $handler = new DeleteExpiredReportsHandler($reportRepository, PlatformSettingsTestFactory::provider());
        $handler(new DeleteExpiredReports());
    }

    public function testHandlerDeletesReportsOlderThanConfiguredRetention(): void
    {
        $reportRepository = $this->createMock(ReportRepository::class);
        $reportRepository
            ->expects(self::once())
            ->method('deleteReportsOlderThan')
            ->with(self::callback(static function (\DateTimeImmutable $threshold): bool {
                $expected = new \DateTimeImmutable('-30 days', new \DateTimeZone('UTC'));

                return abs($threshold->getTimestamp() - $expected->getTimestamp()) <= 1;
            }))
        ;

        $handler = new DeleteExpiredReportsHandler($reportRepository, PlatformSettingsTestFactory::provider(new PlatformSettings('token')->setReportRetentionDays(30)));
        $handler(new DeleteExpiredReports());
    }
}
