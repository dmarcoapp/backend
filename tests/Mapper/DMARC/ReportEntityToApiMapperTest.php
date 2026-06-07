<?php

declare(strict_types=1);

namespace App\Tests\Mapper\DMARC;

use App\DTO\Output\DMARC\ReportApi;
use App\Entity\DMARC\Domain;
use App\Entity\DMARC\Report;
use App\Entity\Email\Email;
use App\Entity\User\User;
use App\Mapper\DMARC\ReportEntityToApiMapper;
use App\Repository\DMARC\DomainRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(ReportEntityToApiMapper::class)]
final class ReportEntityToApiMapperTest extends TestCase
{
    public function testLoadAndPopulateMapsReportFields(): void
    {
        $user = new User();
        $user
            ->setEmail('mapper-user@example.com')
            ->setName('Mapper User')
            ->setSharedPostboxIdentifierToken('mapper-token')
        ;
        $this->setEntityId($user, Uuid::v4());

        $email = new Email();
        $email
            ->setCreatedAt(new \DateTimeImmutable())
            ->setFromAddress('sender@example.com')
            ->setToAddress('inbound@example.com')
            ->setMessageId('<msg@example.com>')
            ->setAttachmentBucket('bucket')
            ->setAttachmentKey('key')
            ->setAttachmentFilename('report.xml')
            ->setAttachmentContentType('application/xml')
            ->setOwner($user)
        ;

        $report = new Report();
        $reportId = Uuid::v4();
        $this->setEntityId($report, $reportId);
        $report
            ->setEmail($email)
            ->setReportingOrganization('Example Org')
            ->setReportingOrganizationEmail('dmarc@example.org')
            ->setReportingOrganizationExtraContact('https://example.org/contact')
            ->setReportId('report-1')
            ->setIsVerified(true)
            ->setBeginDate(new \DateTimeImmutable('-2 days'))
            ->setEndDate(new \DateTimeImmutable('-1 day'))
            ->setDomain('example.com')
            ->setSumCount(10)
        ;

        $domain = new Domain($user, 'example.com');
        $domainId = Uuid::v4();
        $this->setEntityId($domain, $domainId);

        $domainRepository = $this->createMock(DomainRepository::class);
        $domainRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->willReturn($domain)
        ;

        $mapper = new ReportEntityToApiMapper($domainRepository);

        $dto = $mapper->load($report, ReportApi::class, []);
        $mapper->populate($report, $dto, []);

        self::assertSame($reportId, $dto->id);
        self::assertSame('sender@example.com', $dto->fromAddress);
        self::assertSame('Example Org', $dto->reportingOrganization);
        self::assertSame($domainId->toRfc4122(), $dto->domainId);
        self::assertTrue($dto->isVerified);
    }

    public function testLoadThrowsWhenReportIdIsMissing(): void
    {
        $mapper = new ReportEntityToApiMapper($this->createStub(DomainRepository::class));

        $this->expectException(\LogicException::class);
        $mapper->load(new Report(), ReportApi::class, []);
    }

    private function setEntityId(object $entity, Uuid $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
