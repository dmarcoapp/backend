<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler\DMARC;

use App\Entity\DMARC\Report;
use App\Entity\DMARC\ReportRecord;
use App\Entity\Email\Email;
use App\Message\DMARC\ProcessReport;
use App\MessageHandler\DMARC\ProcessReportHandler;
use App\Repository\DMARC\ReportRecordRepository;
use App\Repository\DMARC\ReportRepository;
use App\Service\DMARC\ReportXMLProcessor;
use App\Service\IpLookup\IpLookupInterface;
use App\Tests\Api\ApiIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(ProcessReportHandler::class)]
final class ProcessReportHandlerTest extends ApiIntegrationTestCase
{
    public function testInvokePopulatesReportAndRecords(): void
    {
        $user = $this->createVerifiedUser('report-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $email = new Email();
        $email
            ->setCreatedAt(new \DateTimeImmutable())
            ->setFromAddress('noreply-dmarc-support@google.com')
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
            ->setRawXML($this->validXml())
        ;
        $email->setReport($report);

        $this->entityManager->persist($email);
        $this->entityManager->persist($report);
        $this->entityManager->flush();

        $ipLookup = $this->createStub(IpLookupInterface::class);
        $ipLookup->method('lookup')->willReturn([
            'name' => 'Example Org',
            'country' => 'HU',
            'email' => [
                'abuse' => 'abuse@example.com',
                'tech' => 'tech@example.com',
            ],
        ]);

        $handler = new ProcessReportHandler(
            self::getContainer()->get(ReportRepository::class),
            $this->entityManager,
            new ReportXMLProcessor(new NullLogger(), $this->schemaSource()),
            $ipLookup,
            $this->createStub(LoggerInterface::class)
        );

        $handler(new ProcessReport($report->getId()->toRfc4122()));

        $this->entityManager->refresh($report);

        self::assertTrue($report->isProcessed());
        self::assertSame('google.com', $report->getReportingOrganization());
        self::assertSame('noreply-dmarc-support@google.com', $report->getReportingOrganizationEmail());
        self::assertSame('example.com', $report->getDomain());
        self::assertSame(1, $report->getSumCount());
        self::assertSame(1.0, $report->getDmarcCompliance());
        self::assertSame(1.0, $report->getSpfCompliance());
        self::assertSame(1.0, $report->getDkimCompliance());
        self::assertTrue($report->isVerified());

        $recordRepository = self::getContainer()->get(ReportRecordRepository::class);
        $records = $recordRepository->findBy(['report' => $report]);

        self::assertCount(1, $records);

        $record = $records[0];
        self::assertInstanceOf(ReportRecord::class, $record);
        self::assertSame('192.0.2.1', $record->getSourceIp());
        self::assertSame('pass', $record->getDkimAuth());
        self::assertSame('example.com', $record->getDkimDomain());
        self::assertSame('selector', $record->getDkimSelector());
        self::assertSame('example.com', $record->getSpfDomain());
        self::assertSame('Example Org', $record->getSourceIpInfo()?->getOrgName());
        self::assertSame('HU', $record->getSourceIpInfo()?->getOrgCountry());
    }

    public function testInvokeReturnsWhenReportMissing(): void
    {
        $handler = new ProcessReportHandler(
            self::getContainer()->get(ReportRepository::class),
            $this->entityManager,
            new ReportXMLProcessor(new NullLogger(), $this->schemaSource()),
            $this->createStub(IpLookupInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $handler(new ProcessReport(Uuid::v4()->toRfc4122()));

        self::assertTrue(true);
    }

    public function testInvokeReturnsWhenReportAlreadyProcessed(): void
    {
        $report = $this->createReportWithXml($this->validXml());
        $report->setIsProcessed(true);
        $this->entityManager->flush();

        $handler = new ProcessReportHandler(
            self::getContainer()->get(ReportRepository::class),
            $this->entityManager,
            new ReportXMLProcessor(new NullLogger(), $this->schemaSource()),
            $this->createStub(IpLookupInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $handler(new ProcessReport($report->getId()->toRfc4122()));

        $this->entityManager->refresh($report);
        self::assertTrue($report->isProcessed());
    }

    public function testInvokeThrowsWhenXmlEmpty(): void
    {
        $report = $this->createReportWithXml('');

        $handler = new ProcessReportHandler(
            self::getContainer()->get(ReportRepository::class),
            $this->entityManager,
            new ReportXMLProcessor(new NullLogger(), $this->schemaSource()),
            $this->createStub(IpLookupInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $this->expectException(\LogicException::class);

        $handler(new ProcessReport($report->getId()->toRfc4122()));
    }

    public function testInvokeThrowsWhenDeserializeFails(): void
    {
        $report = $this->createReportWithXml('<feedback>');

        $handler = new ProcessReportHandler(
            self::getContainer()->get(ReportRepository::class),
            $this->entityManager,
            new ReportXMLProcessor(new NullLogger(), $this->schemaSource()),
            $this->createStub(IpLookupInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $handler(new ProcessReport($report->getId()->toRfc4122()));
    }

    public function testInvokeLogsWhenIpLookupFails(): void
    {
        $report = $this->createReportWithXml($this->validXml('203.0.113.10'));

        $ipLookup = $this->createStub(IpLookupInterface::class);
        $ipLookup->method('lookup')->willThrowException(new \InvalidArgumentException('bad ip'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with('Failed to lookup IP address.', self::arrayHasKey('ip'))
        ;

        $handler = new ProcessReportHandler(
            self::getContainer()->get(ReportRepository::class),
            $this->entityManager,
            new ReportXMLProcessor(new NullLogger(), $this->schemaSource()),
            $ipLookup,
            $logger
        );

        $handler(new ProcessReport($report->getId()->toRfc4122()));

        $this->entityManager->refresh($report);
        self::assertTrue($report->isProcessed());
        self::assertFalse($report->getRecords()->isEmpty());
    }

    public function testInvokeMarksReportAsNotVerifiedWhenSenderDoesNotMatch(): void
    {
        $user = $this->createVerifiedUser('report-fail-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $email = new Email();
        $email
            ->setCreatedAt(new \DateTimeImmutable())
            ->setFromAddress('other@example.com')
            ->setToAddress('inbound@example.com')
            ->setMessageId('<message-mismatch@example.com>')
            ->setAttachmentBucket('bucket')
            ->setAttachmentKey('path/report.xml')
            ->setAttachmentFilename('report.xml')
            ->setAttachmentContentType('application/xml')
            ->setOwner($user)
        ;

        $report = new Report();
        $report
            ->setEmail($email)
            ->setRawXML($this->validXml())
        ;
        $email->setReport($report);

        $this->entityManager->persist($email);
        $this->entityManager->persist($report);
        $this->entityManager->flush();

        $handler = new ProcessReportHandler(
            self::getContainer()->get(ReportRepository::class),
            $this->entityManager,
            new ReportXMLProcessor(new NullLogger(), $this->schemaSource()),
            $this->createStub(IpLookupInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $handler(new ProcessReport($report->getId()->toRfc4122()));

        $this->entityManager->refresh($report);

        self::assertFalse($report->isVerified());
    }

    public function testInvokeMarksKnownSenderOrganizationPairsAsVerified(): void
    {
        $report = $this->createReportWithXml(
            $this->validXmlForReporter('Enterprise Outlook', 'dmarcreport@microsoft.com')
        );

        $email = $report->getEmail();
        self::assertInstanceOf(Email::class, $email);
        $email->setFromAddress('dmarcreport@microsoft.com');
        $this->entityManager->flush();

        $handler = new ProcessReportHandler(
            self::getContainer()->get(ReportRepository::class),
            $this->entityManager,
            new ReportXMLProcessor(new NullLogger(), $this->schemaSource()),
            $this->createStub(IpLookupInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $handler(new ProcessReport($report->getId()->toRfc4122()));

        $this->entityManager->refresh($report);

        self::assertTrue($report->isVerified());
    }

    private function schemaSource(): string
    {
        $path = \dirname(__DIR__, 3).'/config/dmarc/rua.xsd';

        return (string) file_get_contents($path);
    }

    private function createReportWithXml(string $xml): Report
    {
        $user = $this->createVerifiedUser('report-xml-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $email = new Email();
        $email
            ->setCreatedAt(new \DateTimeImmutable())
            ->setFromAddress('noreply-dmarc-support@google.com')
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
            ->setRawXML($xml)
        ;
        $email->setReport($report);

        $this->entityManager->persist($email);
        $this->entityManager->persist($report);
        $this->entityManager->flush();

        return $report;
    }

    private function validXml(string $sourceIp = '192.0.2.1'): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<feedback>
  <report_metadata>
    <org_name>google.com</org_name>
    <email>noreply-dmarc-support@google.com</email>
    <report_id>test-report</report_id>
    <date_range>
      <begin>1700000000</begin>
      <end>1700086400</end>
    </date_range>
  </report_metadata>
  <policy_published>
    <domain>example.com</domain>
    <adkim>r</adkim>
    <aspf>r</aspf>
    <p>none</p>
    <sp>none</sp>
    <pct>100</pct>
  </policy_published>
  <record>
    <row>
      <source_ip>{$sourceIp}</source_ip>
      <count>1</count>
      <policy_evaluated>
        <disposition>none</disposition>
        <dkim>pass</dkim>
        <spf>pass</spf>
      </policy_evaluated>
    </row>
    <identifiers>
      <header_from>example.com</header_from>
    </identifiers>
    <auth_results>
      <dkim>
        <domain>example.com</domain>
        <selector>selector</selector>
        <result>pass</result>
      </dkim>
      <spf>
        <domain>example.com</domain>
        <result>pass</result>
      </spf>
    </auth_results>
  </record>
</feedback>
XML;
    }

    private function validXmlForReporter(string $orgName, string $email): string
    {
        return str_replace(
            ['<org_name>google.com</org_name>', '<email>noreply-dmarc-support@google.com</email>'],
            [sprintf('<org_name>%s</org_name>', $orgName), sprintf('<email>%s</email>', $email)],
            $this->validXml(),
        );
    }
}
