<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Controller\DMARC\ReportRecordController;
use App\DTO\Input\Common\Pagination;
use App\Entity\DMARC\Report;
use App\Entity\DMARC\ReportRecord;
use App\Entity\Email\Email;
use App\Enum\DMARC\DispositionType;
use App\Enum\DMARC\DKIMAlign;
use App\Enum\DMARC\SPFAlign;
use App\Enum\DMARC\SPFResult;
use App\Repository\DMARC\ReportRecordRepository;
use Symfonycasts\MicroMapper\MicroMapperInterface;

/**
 * @internal
 *
 * @coversNothing
 */
final class ReportRecordControllerTest extends ApiIntegrationTestCase
{
    public function testListByReportReturnsTheRecords(): void
    {
        $report = $this->createReportWithRecord();
        $recordRepository = self::getContainer()->get(ReportRecordRepository::class);
        self::assertCount(1, $recordRepository->findBy(['report' => $report]));

        $controller = new ReportRecordController(
            self::getContainer()->get(MicroMapperInterface::class),
            $recordRepository
        );

        $response = $controller->listByReport(
            $report,
            new Pagination(page: 1, pageSize: 5),
        );

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('items', $payload);
        self::assertArrayHasKey('meta', $payload);
    }

    private function createReportWithRecord(): Report
    {
        $email = new Email();
        $email
            ->setCreatedAt(new \DateTimeImmutable())
            ->setFromAddress('reporter@example.com')
            ->setToAddress('inbound@example.com')
            ->setMessageId('<message@example.com>')
            ->setAttachmentFilename('report.xml')
            ->setAttachmentContentType('application/xml')
        ;

        $report = new Report();
        $report
            ->setEmail($email)
            ->setRawXML('<xml/>')
            ->setIsProcessed(true)
            ->setBeginDate(new \DateTimeImmutable('-1 day'))
            ->setEndDate(new \DateTimeImmutable())
            ->setDomain('example.com')
            ->setSumCount(1)
            ->setDmarcCompliance(1.0)
            ->setSpfCompliance(1.0)
            ->setDkimCompliance(1.0)
        ;

        $record = new ReportRecord();
        $record
            ->setReport($report)
            ->setSourceIp('192.0.2.1')
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

        $report->addRecord($record);
        $email->setReport($report);

        $this->entityManager->persist($email);
        $this->entityManager->persist($report);
        $this->entityManager->persist($record);
        $this->entityManager->flush();

        return $report;
    }
}
