<?php

declare(strict_types=1);

namespace App\Tests\DTO\Output;

use App\DTO\Output\DMARC\IpInfoApi;
use App\DTO\Output\DMARC\ReportRecordApi;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(IpInfoApi::class)]
#[CoversClass(ReportRecordApi::class)]
final class AdditionalOutputDtosTest extends TestCase
{
    public function testIpInfoApiDefaultsAreNull(): void
    {
        $dto = new IpInfoApi();

        self::assertNull($dto->orgName);
        self::assertNull($dto->orgCountry);
        self::assertNull($dto->orgAbuseEmail);
        self::assertNull($dto->orgTechEmail);
    }

    public function testReportRecordApiSetsId(): void
    {
        $id = Uuid::v4();
        $dto = new ReportRecordApi($id);

        self::assertSame($id, $dto->id);
    }
}
