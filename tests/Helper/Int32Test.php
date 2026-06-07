<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Helper\Int32;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class Int32Test extends TestCase
{
    public function testToSignedReturnsSameForPositiveInt32Range(): void
    {
        self::assertSame(123, Int32::toSigned(123));
        self::assertSame(0x7FFFFFFF, Int32::toSigned(0x7FFFFFFF));
    }

    public function testToSignedConvertsOverflowToNegative(): void
    {
        self::assertSame(-2147483648, Int32::toSigned(0x80000000));
        self::assertSame(-1, Int32::toSigned(0xFFFFFFFF));
    }
}
