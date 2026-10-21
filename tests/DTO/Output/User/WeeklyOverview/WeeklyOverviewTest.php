<?php

declare(strict_types=1);

namespace App\Tests\DTO\Output\User\WeeklyOverview;

use App\DTO\Output\User\WeeklyOverview\TrafficTotals;
use App\DTO\Output\User\WeeklyOverview\WeeklyOverview;
use App\Tests\Service\User\DMARC\WeeklyOverviewFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(WeeklyOverview::class)]
#[CoversClass(TrafficTotals::class)]
final class WeeklyOverviewTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool, string}>
     */
    public static function periods(): iterable
    {
        yield 'same month' => ['2026-10-14', '2026-10-20', false, 'Oct 14–20'];
        yield 'same month with year' => ['2026-10-14', '2026-10-20', true, 'Oct 14–20, 2026'];
        yield 'across months' => ['2026-09-28', '2026-10-04', true, 'Sep 28–Oct 4, 2026'];
        yield 'across years' => ['2025-12-29', '2026-01-04', false, 'Dec 29, 2025–Jan 4, 2026'];
    }

    #[DataProvider('periods')]
    public function testPeriodLabel(string $start, string $end, bool $withYear, string $expected): void
    {
        $overview = new WeeklyOverview(
            periodStart: new \DateTimeImmutable($start),
            periodEnd: new \DateTimeImmutable($end),
            previousPeriodStart: new \DateTimeImmutable($start.' -7 days'),
            previousPeriodEnd: new \DateTimeImmutable($end.' -7 days'),
            reportCount: 1,
            reportingOrganizations: [],
            traffic: new TrafficTotals(),
            previousTraffic: new TrafficTotals(),
            domains: [],
            findings: [],
            reportAddress: 'token@example.test',
        );

        self::assertSame($expected, $overview->periodLabel($withYear));
    }

    public function testComparesWithThePreviousWeek(): void
    {
        $overview = WeeklyOverviewFixture::needingAttention();

        self::assertSame('Oct 5–11', $overview->previousPeriodLabel());
        self::assertEqualsWithDelta(15.85, $overview->messagesChangePercent(), 0.01);
        // 8494 of 10649 against 7914 of 9192: 79.76% against 86.10%.
        self::assertEqualsWithDelta(-6.33, $overview->passRateChangePoints(), 0.01);
    }

    public function testHasNoChangeWithoutAPreviousWeek(): void
    {
        $overview = WeeklyOverviewFixture::withoutReports();

        self::assertFalse($overview->hasReports());
        self::assertNull($overview->messagesChangePercent());
        self::assertNull($overview->passRateChangePoints());
        self::assertNull($overview->traffic->passRate());
    }

    public function testListsOnlyTheDomainsNeedingAttention(): void
    {
        $attention = WeeklyOverviewFixture::needingAttention()->domainsNeedingAttention();

        self::assertCount(1, $attention);
        self::assertSame('example.org', $attention[0]->domain);
        self::assertSame([], WeeklyOverviewFixture::healthy()->domainsNeedingAttention());
    }

    public function testTrafficTotalsAddUp(): void
    {
        $sum = new TrafficTotals(10, 8, 1, 1)->add(new TrafficTotals(30, 22, 0, 8));

        self::assertEquals(new TrafficTotals(40, 30, 1, 9), $sum);
        self::assertSame(0.75, $sum->passRate());
    }
}
