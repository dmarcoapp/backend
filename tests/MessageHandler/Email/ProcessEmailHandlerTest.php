<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler\Email;

use App\Entity\DMARC\Domain;
use App\Entity\DMARC\Report;
use App\Entity\Email\Email;
use App\Entity\User\User;
use App\Message\DMARC\ProcessReport;
use App\Message\Email\ProcessEmail;
use App\MessageHandler\Email\ProcessEmailHandler;
use App\Repository\DMARC\DomainRepository;
use App\Repository\DMARC\ReportRepository;
use App\Repository\Email\EmailRepository;
use App\Service\DMARC\ReportXMLProcessor;
use App\Tests\Api\ApiIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(ProcessEmailHandler::class)]
final class ProcessEmailHandlerTest extends ApiIntegrationTestCase
{
    public function testInvokeThrowsWhenEmailMissing(): void
    {
        $handler = $this->createHandler(
            self::getContainer()->get(EmailRepository::class),
            $this->createStub(MessageBusInterface::class),
            $this->createStub(LoggerInterface::class),
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $handler(new ProcessEmail(Uuid::v4()->toRfc4122()));
    }

    public function testInvokeDispatchesReportWhenXmlIsValidAndCreatesDomain(): void
    {
        $emailRepository = self::getContainer()->get(EmailRepository::class);
        $domainRepository = self::getContainer()->get(DomainRepository::class);
        $reportRepository = self::getContainer()->get(ReportRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);

        $user = $this->createAccount('owner-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');
        $xmlContent = $this->validXml();
        $email = $this->createEmail($user, 'report.xml', $xmlContent);
        $emailRepository->save($email);

        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (ProcessReport $message): bool {
                return Uuid::isValid($message->reportId);
            }))
            ->willReturnCallback(static fn (ProcessReport $message): Envelope => new Envelope($message))
        ;

        $handler = $this->createHandler(
            $emailRepository,
            $messageBus,
            $this->createStub(LoggerInterface::class),
        );

        $handler(new ProcessEmail($email->getId()->toRfc4122()));

        $report = $reportRepository->findOneBy(['email' => $email]);
        self::assertNotNull($report);
        self::assertSame($xmlContent, $report->getRawXML());

        // The report holds the XML now, so the email does not keep a second copy.
        $this->entityManager->refresh($email);
        self::assertNull($email->getAttachmentContent());

        $domain = $domainRepository->findOneBy([
            'domain' => 'example.com',
            'user' => $user,
        ]);
        self::assertInstanceOf(Domain::class, $domain);
    }

    public function testInvokeUpdatesExistingReportWhenPresent(): void
    {
        $emailRepository = self::getContainer()->get(EmailRepository::class);
        $reportRepository = self::getContainer()->get(ReportRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);

        $user = $this->createAccount('owner-existing-report-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');
        $xmlContent = $this->validXml('198.51.100.10');
        $email = $this->createEmail($user, 'report.xml', $xmlContent);
        $emailRepository->save($email);

        $existingReport = new Report();
        $existingReport
            ->setEmail($email)
            ->setRawXML('<?xml version="1.0"?><old/>')
        ;
        $this->entityManager->persist($existingReport);
        $this->entityManager->flush();

        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(ProcessReport::class))
            ->willReturnCallback(static fn (ProcessReport $message): Envelope => new Envelope($message))
        ;

        $handler = $this->createHandler(
            $emailRepository,
            $messageBus,
            $this->createStub(LoggerInterface::class),
        );

        $handler(new ProcessEmail($email->getId()->toRfc4122()));

        $this->entityManager->refresh($existingReport);
        self::assertSame($xmlContent, $existingReport->getRawXML());
    }

    public function testInvokeHandsOnAReportAnEarlierRunAlreadyStored(): void
    {
        $emailRepository = self::getContainer()->get(EmailRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);

        // What a redelivery finds when the first run stored the report, and so
        // cleared the email, but failed before it dispatched ProcessReport.
        $user = $this->createAccount('owner-redelivered-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');
        $email = $this->createEmail($user, 'report.xml');
        $emailRepository->save($email);

        $report = new Report();
        $report
            ->setEmail($email)
            ->setRawXML($this->validXml())
        ;
        $this->entityManager->persist($report);
        $this->entityManager->flush();
        $this->entityManager->refresh($email);

        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (ProcessReport $message): bool => $message->reportId === $report->getId()?->toRfc4122()))
            ->willReturnCallback(static fn (ProcessReport $message): Envelope => new Envelope($message))
        ;

        $handler = $this->createHandler(
            $emailRepository,
            $messageBus,
            $this->createStub(LoggerInterface::class),
        );

        $handler(new ProcessEmail($email->getId()->toRfc4122()));
    }

    public function testInvokeLogsWhenExtensionUnsupported(): void
    {
        $emailRepository = self::getContainer()->get(EmailRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $user = $this->createAccount('owner-unsupported-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');
        $email = $this->createEmail($user, 'report.zip');
        $emailRepository->save($email);

        $messageBus->expects(self::never())->method('dispatch');
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Unsupported attachment extension.',
                self::callback(static fn (array $context): bool => 'zip' === ($context['extension'] ?? null)),
            )
        ;

        $handler = $this->createHandler(
            $emailRepository,
            $messageBus,
            $logger,
        );

        $handler(new ProcessEmail($email->getId()->toRfc4122()));
    }

    private function createHandler(
        EmailRepository $emailRepository,
        MessageBusInterface $messageBus,
        LoggerInterface $logger,
    ): ProcessEmailHandler {
        return new ProcessEmailHandler(
            $emailRepository,
            self::getContainer()->get(DomainRepository::class),
            self::getContainer()->get(ReportRepository::class),
            new ReportXMLProcessor(new NullLogger(), $this->schemaSource(), $this->rfc9990SchemaSource()),
            $this->entityManager,
            $messageBus,
            $logger,
        );
    }

    private function createEmail(User $user, string $filename, ?string $content = null): Email
    {
        $email = new Email();
        $email
            ->setCreatedAt(new \DateTimeImmutable('2024-01-01 10:00:00'))
            ->setFromAddress('sender@example.com')
            ->setToAddress('recipient@example.com')
            ->setMessageId('<message@example.com>')
            ->setAttachmentFilename($filename)
            ->setAttachmentContentType('application/xml')
            ->setAttachmentContent($content)
            ->setOwner($user)
        ;

        return $email;
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

    private function validXml(string $sourceIp = '192.0.2.1', string $domain = 'example.com'): string
    {
        return <<<XML
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
    <domain>{$domain}</domain>
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
        <dkim>fail</dkim>
        <spf>fail</spf>
      </policy_evaluated>
    </row>
    <identifiers>
      <header_from>{$domain}</header_from>
    </identifiers>
    <auth_results>
      <dkim>
        <domain>{$domain}</domain>
        <selector>selector</selector>
        <result>pass</result>
      </dkim>
      <spf>
        <domain>{$domain}</domain>
        <result>pass</result>
      </spf>
    </auth_results>
  </record>
</feedback>
XML;
    }
}
