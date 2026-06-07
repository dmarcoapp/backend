<?php

declare(strict_types=1);

namespace App\Tests\Mapper\DMARC;

use App\DTO\Output\DMARC\IpInfoApi;
use App\DTO\Output\DMARC\ReportRecordApi;
use App\Entity\DMARC\IpInfo;
use App\Entity\DMARC\Report;
use App\Entity\DMARC\ReportRecord;
use App\Enum\DMARC\DispositionType;
use App\Enum\DMARC\DKIMAlign;
use App\Enum\DMARC\SPFAlign;
use App\Enum\DMARC\SPFResult;
use App\Mapper\DMARC\ReportRecordEntityToApiMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfonycasts\MicroMapper\MicroMapperInterface;

/**
 * @internal
 */
#[CoversClass(ReportRecordEntityToApiMapper::class)]
final class ReportRecordEntityToApiMapperTest extends TestCase
{
    public function testLoadAndPopulateMapFields(): void
    {
        $reportId = Uuid::v4();
        $recordId = Uuid::v4();

        $report = new Report();
        $this->setEntityId($report, $reportId);

        $ipInfo = new IpInfo();
        $ipInfo->setOrgName('Org');
        $ipInfo->setOrgCountry('HU');

        $record = new ReportRecord();
        $this->setEntityId($record, $recordId);
        $record
            ->setReport($report)
            ->setSourceIp('192.0.2.1')
            ->setSourceIpInfo($ipInfo)
            ->setCount(5)
            ->setDisposition(DispositionType::NONE)
            ->setDkimAlign(DKIMAlign::PASS)
            ->setSpfAlign(SPFAlign::PASS)
            ->setDkimAuth('pass')
            ->setDkimDomain('example.com')
            ->setDkimSelector('selector')
            ->setSpfAuth(SPFResult::PASS)
            ->setSpfDomain('example.com')
        ;

        $ipInfoApi = new IpInfoApi();
        $ipInfoApi->orgName = 'Org';

        $microMapper = $this->createMock(MicroMapperInterface::class);
        $microMapper
            ->expects(self::once())
            ->method('map')
            ->with($ipInfo, IpInfoApi::class)
            ->willReturn($ipInfoApi)
        ;

        $mapper = new ReportRecordEntityToApiMapper($microMapper);

        $dto = $mapper->load($record, ReportRecordApi::class, []);
        $mapper->populate($record, $dto, []);

        self::assertSame($recordId, $dto->id);
        self::assertSame($reportId, $dto->reportId);
        self::assertSame('192.0.2.1', $dto->sourceIp);
        self::assertSame($ipInfoApi, $dto->sourceIpInfo);
        self::assertSame(5, $dto->count);
        self::assertSame(DispositionType::NONE, $dto->disposition);
        self::assertSame(DKIMAlign::PASS, $dto->dkimAlign);
        self::assertSame(SPFAlign::PASS, $dto->spfAlign);
        self::assertSame('pass', $dto->dkimAuth);
        self::assertSame('example.com', $dto->dkimDomain);
        self::assertSame('selector', $dto->dkimSelector);
        self::assertSame(SPFResult::PASS, $dto->spfAuth);
        self::assertSame('example.com', $dto->spfDomain);
    }

    public function testLoadThrowsWhenRecordIdIsMissing(): void
    {
        $mapper = new ReportRecordEntityToApiMapper($this->createStub(MicroMapperInterface::class));

        $this->expectException(\LogicException::class);
        $mapper->load(new ReportRecord(), ReportRecordApi::class, []);
    }

    private function setEntityId(object $entity, Uuid $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
