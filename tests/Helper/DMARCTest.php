<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Enum\DMARC\Domain\ProtectionLevel;
use App\Helper\DMARC;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class DMARCTest extends TestCase
{
    public function testDmarcProtectionLevelPerfect(): void
    {
        $record = 'v=DMARC1; p=reject; pct=100';

        self::assertSame(ProtectionLevel::STRONG, DMARC::dmarcProtectionLevel($record));
    }

    public function testDmarcProtectionLevelMedium(): void
    {
        $record = 'v=DMARC1; p=quarantine; pct=50';

        self::assertSame(ProtectionLevel::MODERATE, DMARC::dmarcProtectionLevel($record));
    }

    public function testDmarcProtectionLevelLow(): void
    {
        $record = 'v=DMARC1; p=quarantine; pct=49';

        self::assertSame(ProtectionLevel::WEAK, DMARC::dmarcProtectionLevel($record));
    }

    public function testDmarcProtectionLevelPctFallbacksTo100(): void
    {
        $record = 'v=DMARC1; p=reject; pct=abc';

        self::assertSame(ProtectionLevel::STRONG, DMARC::dmarcProtectionLevel($record));
    }

    public function testCheckConfigurationMatchesNormalizedEmail(): void
    {
        $record = 'v=DMARC1; rua=mailto:report@example.com, mailto:other@example.com';

        self::assertTrue(DMARC::checkConfiguration($record, 'MAILTO:report@example.com?x=y'));
    }

    public function testCheckConfigurationReturnsFalseWhenMissingRua(): void
    {
        $record = 'v=DMARC1; p=reject;';

        self::assertFalse(DMARC::checkConfiguration($record, 'report@example.com'));
    }

    public function testCheckConfigurationReturnsFalseForEmptyEmail(): void
    {
        $record = 'v=DMARC1; rua=mailto:report@example.com';

        self::assertFalse(DMARC::checkConfiguration($record, '   '));
    }

    public function testCheckConfigurationIgnoresNonMailtoEntries(): void
    {
        $record = 'v=DMARC1; rua=report@example.com, mailto:other@example.com';

        self::assertFalse(DMARC::checkConfiguration($record, 'report@example.com'));
        self::assertTrue(DMARC::checkConfiguration($record, 'other@example.com'));
    }

    public function testDmarcProtectionLevelHandlesCaseAndWhitespace(): void
    {
        $record = 'V=DMARC1; P=REJECT; PCT=100;  ';

        self::assertSame(ProtectionLevel::STRONG, DMARC::dmarcProtectionLevel($record));
    }
}
