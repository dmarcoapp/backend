<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\Auth\AuthLogAction;
use App\Enum\Common\DatabaseLogLevel;
use App\Enum\Common\FiltrationOperator;
use App\Enum\Common\SortDirection;
use App\Enum\DMARC\AlignmentType;
use App\Enum\DMARC\DispositionType;
use App\Enum\DMARC\DKIMAlign;
use App\Enum\DMARC\DKIMResult;
use App\Enum\DMARC\Domain\ProtectionLevel;
use App\Enum\DMARC\SPFAlign;
use App\Enum\DMARC\SPFResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AuthLogAction::class)]
#[CoversClass(DatabaseLogLevel::class)]
#[CoversClass(FiltrationOperator::class)]
#[CoversClass(SortDirection::class)]
#[CoversClass(AlignmentType::class)]
#[CoversClass(DKIMAlign::class)]
#[CoversClass(DKIMResult::class)]
#[CoversClass(DispositionType::class)]
#[CoversClass(SPFAlign::class)]
#[CoversClass(SPFResult::class)]
#[CoversClass(ProtectionLevel::class)]
final class EnumSmokeTest extends TestCase
{
    public function testEnumCasesAreAvailable(): void
    {
        self::assertNotEmpty(AuthLogAction::cases());
        self::assertNotEmpty(DatabaseLogLevel::cases());
        self::assertNotEmpty(FiltrationOperator::cases());
        self::assertNotEmpty(SortDirection::cases());
        self::assertNotEmpty(AlignmentType::cases());
        self::assertNotEmpty(DKIMAlign::cases());
        self::assertNotEmpty(DKIMResult::cases());
        self::assertNotEmpty(DispositionType::cases());
        self::assertNotEmpty(SPFAlign::cases());
        self::assertNotEmpty(SPFResult::cases());
        self::assertNotEmpty(ProtectionLevel::cases());
    }

    public function testProtectionLevelValuesMatchCaseNames(): void
    {
        self::assertSame('weak', ProtectionLevel::WEAK->value);
        self::assertSame('moderate', ProtectionLevel::MODERATE->value);
        self::assertSame('strong', ProtectionLevel::STRONG->value);
    }
}
