<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler\Email;

use App\Entity\DMARC\Domain;
use App\Entity\DMARC\Report;
use App\Entity\Email\Email;
use App\Entity\User\User;
use App\Message\Email\ProcessEmail;
use App\MessageHandler\Email\ProcessEmailHandler;
use App\Repository\DMARC\DomainRepository;
use App\Repository\DMARC\ReportRepository;
use App\Repository\Email\EmailRepository;
use App\Service\DMARC\ReportXMLProcessor;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
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
        $email = $this->createEmailEntityWithOwner();

        $emailRepository = $this->createStub(EmailRepository::class);
        $emailRepository->method('find')->willReturn($email);

        $handler = $this->createHandler($emailRepository, $this->createStub(ReportRepository::class));

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Email ID is missing.');
        $handler(new ProcessEmail(Uuid::v4()->toRfc4122()));
    }

    public function testInvokeThrowsWhenAttachmentFilenameMissing(): void
    {
        $email = $this->createEmailEntityWithOwner();
        $this->setEntityId($email, Uuid::v4());

        $emailRepository = $this->createStub(EmailRepository::class);
        $emailRepository->method('find')->willReturn($email);

        $handler = $this->createHandler($emailRepository, $this->createStub(ReportRepository::class));

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Email attachment filename is missing.');
        $handler(new ProcessEmail(Uuid::v4()->toRfc4122()));
    }

    public function testInvokeThrowsWhenAttachmentKeyMissing(): void
    {
        $email = $this->createEmailEntityWithOwner();
        $this->setEntityId($email, Uuid::v4());
        $email->setAttachmentFilename('report.xml');

        $emailRepository = $this->createStub(EmailRepository::class);
        $emailRepository->method('find')->willReturn($email);

        $handler = $this->createHandler($emailRepository, $this->createStub(ReportRepository::class));

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Email attachment key is missing.');
        $handler(new ProcessEmail(Uuid::v4()->toRfc4122()));
    }

    public function testInvokeThrowsWhenEmailOwnerMissing(): void
    {
        $email = new Email();
        $this->setEntityId($email, Uuid::v4());
        $email
            ->setAttachmentFilename('report.xml')
            ->setAttachmentKey('path/report.xml')
        ;

        $emailRepository = $this->createStub(EmailRepository::class);
        $emailRepository->method('find')->willReturn($email);

        $handler = $this->createHandler($emailRepository, $this->createStub(ReportRepository::class));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Email owner is missing.');
        $handler(new ProcessEmail(Uuid::v4()->toRfc4122()));
    }

    public function testInvokeThrowsWhenReportIdMissing(): void
    {
        $email = $this->createEmailEntityWithOwner();
        $this->setEntityId($email, Uuid::v4());
        $email->setAttachmentFilename('report.xml');
        $email->setAttachmentKey('path/report.xml');

        $emailRepository = $this->createStub(EmailRepository::class);
        $emailRepository->method('find')->willReturn($email);

        $reportRepository = $this->createStub(ReportRepository::class);
        $reportRepository->method('findOneBy')->with(['email' => $email])->willReturn(new Report());

        $domainRepository = $this->createStub(DomainRepository::class);
        $domainRepository
            ->method('findOneBy')
            ->willReturn(new Domain($email->getOwner() ?? new User(), 'example.com'))
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');
        $entityManager->expects(self::never())->method('persist');
        $entityManager->method('getConnection')->willReturn($this->createConnectionStub());

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $storage = $this->createStub(AwsS3V3Adapter::class);
        $storage->method('fileExists')->willReturn(true);
        $storage->method('read')->willReturn($this->validXml());

        $handler = new ProcessEmailHandler(
            $emailRepository,
            $domainRepository,
            $reportRepository,
            new ReportXMLProcessor(new NullLogger(), $this->schemaSource()),
            $entityManager,
            $messageBus,
            $storage,
            $this->createStub(LoggerInterface::class),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Report ID is missing.');
        $handler(new ProcessEmail(Uuid::v4()->toRfc4122()));
    }

    private function createHandler(EmailRepository $emailRepository, ReportRepository $reportRepository): ProcessEmailHandler
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($this->createConnectionStub());

        return new ProcessEmailHandler(
            $emailRepository,
            $this->createStub(DomainRepository::class),
            $reportRepository,
            new ReportXMLProcessor(new NullLogger(), $this->schemaSource()),
            $entityManager,
            $this->createStub(MessageBusInterface::class),
            $this->createStub(AwsS3V3Adapter::class),
            $this->createStub(LoggerInterface::class),
        );
    }

    private function createEmailEntityWithOwner(): Email
    {
        $owner = new User();
        $owner->setEmail('owner@example.com');
        $owner->setName('Owner');
        $owner->setSharedPostboxIdentifierToken('owner-token');
        $this->setEntityId($owner, Uuid::v4());

        $email = new Email();
        $email->setOwner($owner);

        return $email;
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
