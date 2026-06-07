<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Helper\Date;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class DateTest extends TestCase
{
    public function testCreateDatePeriodDefaults(): void
    {
        $days = 3;
        $period = Date::createDatePeriod($days);

        $end = new \DateTimeImmutable('yesterday');
        $start = $end->sub(new \DateInterval('P'.($days - 1).'D'));

        $expected = [];
        for ($i = 0; $i < $days; ++$i) {
            $expected[] = $start->add(new \DateInterval('P'.$i.'D'))->format('Y-m-d');
        }

        $actual = [];
        foreach ($period as $date) {
            $actual[] = $date->format('Y-m-d');
        }

        self::assertSame($expected, $actual);
    }

    public function testCreateDatePeriodPreviousPeriod(): void
    {
        $days = 2;
        $period = Date::createDatePeriod($days, true);

        $end = new \DateTimeImmutable('-'.$days.'days');
        $start = $end->sub(new \DateInterval('P'.($days - 1).'D'));

        $expected = [
            $start->format('Y-m-d'),
            $start->add(new \DateInterval('P1D'))->format('Y-m-d'),
        ];

        $actual = [];
        foreach ($period as $date) {
            $actual[] = $date->format('Y-m-d');
        }

        self::assertSame($expected, $actual);
    }
}
