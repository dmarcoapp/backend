<?php

declare(strict_types=1);

namespace App\Tests\Repository\DMARC;

use App\DTO\Input\Common\Pagination;
use App\Entity\DMARC\Report;
use App\Entity\DMARC\ReportRecord;
use App\Entity\Email\Email;
use App\Entity\User\User;
use App\Enum\DMARC\DispositionType;
use App\Enum\DMARC\DKIMAlign;
use App\Enum\DMARC\SPFAlign;
use App\Enum\DMARC\SPFResult;
use App\Repository\DMARC\ReportRecordRepository;
use App\Repository\DMARC\ReportRepository;
use App\Tests\Api\ApiIntegrationTestCase;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @internal
 *
 * @coversNothing
 */
final class ReportRecordRepositoryTest extends ApiIntegrationTestCase
{
    public function testGetReportRecordsForReportPaginatedFiltersByReport(): void
    {
        $user = $this->createVerifiedUser('records-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $report = $this->createReport($user, 'example.com');
        $otherReport = $this->createReport($user, 'other.example');

        $recordA = $this->createRecord($report, '192.0.2.1');
        $recordB = $this->createRecord($report, '192.0.2.2');
        $recordC = $this->createRecord($otherReport, '192.0.2.3');

        $report->addRecord($recordA);
        $report->addRecord($recordB);
        $otherReport->addRecord($recordC);

        $this->entityManager->persist($recordA);
        $this->entityManager->persist($recordB);
        $this->entityManager->persist($recordC);
        $this->entityManager->flush();

        $repository = self::getContainer()->get(ReportRecordRepository::class);
        $this->entityManager->clear();
        $managedReport = self::getContainer()->get(ReportRepository::class)->find($report->getId());
        self::assertNotNull($managedReport);
        self::assertCount(2, $repository->findBy(['report' => $managedReport]));
        $paginator = $repository->getReportRecordsForReportPaginated($managedReport, new Pagination(page: 1, pageSize: 10));

        self::assertInstanceOf(Paginator::class, $paginator);
    }

    private function createReport(User $user, string $domain): Report
    {
        $email = new Email();
        $email
            ->setCreatedAt(new \DateTimeImmutable())
            ->setFromAddress('reporter@example.com')
            ->setToAddress('inbound@example.com')
            ->setMessageId('<message@example.com>')
            ->setAttachmentBucket('bucket')
            ->setAttachmentKey('path/report.xml')
            ->setAttachmentFilename('report.xml')
            ->setAttachmentContentType('application/xml')
            ->setOwner($user)
        ;

        $report = new Report();
        $report
            ->setEmail($email)
            ->setRawXML('<xml/>')
            ->setIsProcessed(true)
            ->setBeginDate(new \DateTimeImmutable('-1 day'))
            ->setEndDate(new \DateTimeImmutable())
            ->setDomain($domain)
            ->setSumCount(1)
            ->setDmarcCompliance(1.0)
            ->setSpfCompliance(1.0)
            ->setDkimCompliance(1.0)
        ;

        $email->setReport($report);

        $this->entityManager->persist($email);
        $this->entityManager->persist($report);
        $this->entityManager->flush();

        return $report;
    }

    private function createRecord(Report $report, string $sourceIp): ReportRecord
    {
        return (new ReportRecord())
            ->setReport($report)
            ->setSourceIp($sourceIp)
            ->setCount(1)
            ->setDisposition(DispositionType::NONE)
            ->setDkimAlign(DKIMAlign::PASS)
            ->setSpfAlign(SPFAlign::PASS)
            ->setDkimAuth('pass')
            ->setDkimDomain('example.com')
            ->setDkimSelector('selector')
            ->setSpfAuth(SPFResult::PASS)
            ->setSpfDomain('example.com')
        ;
    }
}
