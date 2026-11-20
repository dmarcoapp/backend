<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler\Email;

use App\Entity\DMARC\Domain;
use App\Entity\DMARC\Report;
use App\Entity\Email\Email;
use App\Message\Email\ProcessEmail;
use App\MessageHandler\Email\ProcessEmailHandler;
use App\Repository\DMARC\DomainRepository;
use App\Repository\DMARC\ReportRepository;
use App\Repository\Email\EmailRepository;
use App\Service\DMARC\ReportXMLProcessor;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(ProcessEmailHandler::class)]
final class ProcessEmailHandlerUnitTest extends TestCase
{
    public function testInvokeThrowsWhenEmailIdMissing(): void
    {
        $email = new Email();

        $emailRepository = $this->createStub(EmailRepository::class);
        $emailRepository->method('find')->willReturn($email);

        $handler = $this->createHandler($emailRepository, $this->createStub(ReportRepository::class));

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Email ID is missing.');
        $handler(new ProcessEmail(Uuid::v4()->toRfc4122()));
    }

    public function testInvokeThrowsWhenAttachmentFilenameMissing(): void
    {
        $email = new Email();
        $this->setEntityId($email, Uuid::v4());

        $emailRepository = $this->createStub(EmailRepository::class);
        $emailRepository->method('find')->willReturn($email);

        $handler = $this->createHandler($emailRepository, $this->createStub(ReportRepository::class));

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Email attachment filename is missing.');
        $handler(new ProcessEmail(Uuid::v4()->toRfc4122()));
    }

    public function testInvokeThrowsWhenAttachmentContentMissing(): void
    {
        // What an email that arrived before the upgrade, and still pointed at
        // object storage, looks like to the handler.
        $email = new Email();
        $this->setEntityId($email, Uuid::v4());
        $email->setAttachmentFilename('report.xml');

        $emailRepository = $this->createStub(EmailRepository::class);
        $emailRepository->method('find')->willReturn($email);

        $handler = $this->createHandler($emailRepository, $this->createStub(ReportRepository::class));

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Email attachment content is missing.');
        $handler(new ProcessEmail(Uuid::v4()->toRfc4122()));
    }

    public function testInvokeThrowsWhenReportIdMissing(): void
    {
        $email = new Email();
        $this->setEntityId($email, Uuid::v4());
        $email->setAttachmentFilename('report.xml');
        $email->setAttachmentContent($this->validXml());

        $emailRepository = $this->createStub(EmailRepository::class);
        $emailRepository->method('find')->willReturn($email);

        // No copy of the report has arrived, and the email's own report is
        // there already.
        $reportRepository = $this->createStub(ReportRepository::class);
        $reportRepository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): ?Report => ['email' => $email] === $criteria ? new Report() : null,
        );

        $domainRepository = $this->createStub(DomainRepository::class);
        $domainRepository->method('findOneBy')->willReturn(new Domain('example.com'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');
        $entityManager->expects(self::never())->method('persist');
        $entityManager->method('getConnection')->willReturn($this->createConnectionStub());

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $handler = new ProcessEmailHandler(
            $emailRepository,
            $domainRepository,
            $reportRepository,
            new ReportXMLProcessor(new NullLogger(), $this->schemaSource(), $this->rfc9990SchemaSource()),
            $entityManager,
            $messageBus,
            $this->createStub(LoggerInterface::class),
        );

        try {
            $handler(new ProcessEmail(Uuid::v4()->toRfc4122()));
            self::fail('The missing report ID went unnoticed.');
        } catch (\LogicException $e) {
            self::assertSame('Report ID is missing.', $e->getMessage());
        }

        // The report took the XML over before the flush, so the email holds no copy.
        self::assertNull($email->getAttachmentContent());
    }

    private function createHandler(EmailRepository $emailRepository, ReportRepository $reportRepository): ProcessEmailHandler
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($this->createConnectionStub());

        return new ProcessEmailHandler(
            $emailRepository,
            $this->createStub(DomainRepository::class),
            $reportRepository,
            new ReportXMLProcessor(new NullLogger(), $this->schemaSource(), $this->rfc9990SchemaSource()),
            $entityManager,
            $this->createStub(MessageBusInterface::class),
            $this->createStub(LoggerInterface::class),
        );
    }

    private function createConnectionStub(): Connection
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willThrowException(new \RuntimeException());

        return $connection;
    }

    private function setEntityId(object $entity, Uuid $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    private function schemaSource(): string
    {
        $path = \dirname(__DIR__, 3).'/config/dmarc/rua.xsd';

        return (string) file_get_contents($path);
    }

    private function rfc9990SchemaSource(): string
    {
        $path = \dirname(__DIR__, 3).'/config/dmarc/rfc9990.xsd';

        return (string) file_get_contents($path);
    }

    private function validXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feedback>
  <report_metadata>
    <org_name>Example</org_name>
    <email>org@example.com</email>
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
      <source_ip>192.0.2.1</source_ip>
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
}
