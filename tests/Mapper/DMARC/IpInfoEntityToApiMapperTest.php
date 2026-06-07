<?php

declare(strict_types=1);

namespace App\Tests\Mapper\DMARC;

use App\DTO\Output\DMARC\IpInfoApi;
use App\Entity\DMARC\IpInfo;
use App\Mapper\DMARC\IpInfoEntityToApiMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(IpInfoEntityToApiMapper::class)]
final class IpInfoEntityToApiMapperTest extends TestCase
{
    public function testLoadAndPopulateMapsIpInfoFields(): void
    {
        $ipInfo = (new IpInfo())
            ->setOrgName('Example Org')
            ->setOrgCountry('HU')
            ->setOrgAbuseEmail('abuse@example.com')
            ->setOrgTechEmail('tech@example.com')
        ;

        $mapper = new IpInfoEntityToApiMapper();
        $dto = $mapper->load($ipInfo, IpInfoApi::class, []);
        $mapper->populate($ipInfo, $dto, []);

        self::assertSame('Example Org', $dto->orgName);
        self::assertSame('HU', $dto->orgCountry);
        self::assertSame('abuse@example.com', $dto->orgAbuseEmail);
        self::assertSame('tech@example.com', $dto->orgTechEmail);
    }
}
