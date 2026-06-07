<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Helper\Trend;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class TrendTest extends TestCase
{
    public function testCalculateReturnsZeroWhenCurrentIsNull(): void
    {
        self::assertSame(0.0, Trend::calculate(null, 100.0));
    }

    public function testCalculateReturnsZeroWhenPreviousIsNull(): void
    {
        self::assertSame(0.0, Trend::calculate(100.0, null));
    }

    public function testCalculateReturnsZeroWhenPreviousIsZero(): void
    {
        self::assertSame(0.0, Trend::calculate(100.0, 0.0));
    }

    public function testCalculatePositiveTrend(): void
    {
        self::assertSame(20.0, Trend::calculate(120.0, 100.0));
    }

    public function testCalculateNegativeTrend(): void
    {
        self::assertSame(-20.0, Trend::calculate(80.0, 100.0));
    }
}
