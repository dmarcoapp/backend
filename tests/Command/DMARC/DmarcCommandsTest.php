<?php

declare(strict_types=1);

namespace App\Tests\Command\DMARC;

use App\Command\DMARC\Domain\ProcessDomainBulkCommand;
use App\Command\DMARC\Domain\ProcessDomainCommand;
use App\Command\DMARC\Report\ProcessReportBulkCommand;
use App\Command\DMARC\Report\ProcessReportCommand;
use App\Entity\DMARC\Domain;
use App\Entity\DMARC\Report;
use App\Entity\Email\Email;
use App\Entity\User\User;
use App\Message\DMARC\ProcessDomain;
use App\Message\DMARC\ProcessReport;
use App\Repository\DMARC\DomainRepository;
use App\Repository\DMARC\ReportRepository;
use App\Tests\Api\ApiIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(ProcessDomainCommand::class)]
#[CoversClass(ProcessDomainBulkCommand::class)]
#[CoversClass(ProcessReportCommand::class)]
#[CoversClass(ProcessReportBulkCommand::class)]
final class DmarcCommandsTest extends ApiIntegrationTestCase
{
    public function testProcessDomainCommandDispatches(): void
    {
        $domain = $this->createDomain();

        $repository = $this->createStub(DomainRepository::class);
        $repository->method('find')->willReturn($domain);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (ProcessDomain $message) use ($domain): bool {
                return $message->domainId === $domain->getId()->toRfc4122();
            }))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $command = new ProcessDomainCommand($repository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute(['id' => $domain->getId()->toRfc4122()]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessDomainCommandPromptsForId(): void
    {
        $domain = $this->createDomain();

        $repository = $this->createStub(DomainRepository::class);
        $repository->method('find')->willReturn($domain);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (ProcessDomain $message) use ($domain): bool {
                return $message->domainId === $domain->getId()->toRfc4122();
            }))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $command = new ProcessDomainCommand($repository, $messageBus);

        $tester = new CommandTester($command);
        $tester->setInputs([$domain->getId()->toRfc4122()]);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessDomainCommandFailsWhenMissing(): void
    {
        $repository = $this->createStub(DomainRepository::class);
        $repository->method('find')->willReturn(null);

        $command = new ProcessDomainCommand($repository, $this->createStub(MessageBusInterface::class));

        $tester = new CommandTester($command);
        $tester->execute(['id' => 'missing']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testProcessDomainCommandFailsWhenDomainIdIsMissing(): void
    {
        $user = new User();
        $user->setEmail('missing-id-user@example.com');
        $user->setName('Missing Id User');
        $user->setSharedPostboxIdentifierToken('missing-id-token');

        $domain = new Domain($user, 'example.com');

        $repository = $this->createStub(DomainRepository::class);
        $repository->method('find')->willReturn($domain);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $command = new ProcessDomainCommand($repository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute(['id' => 'existing-domain-without-id']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testProcessDomainBulkCommandDispatches(): void
    {
        $domain = $this->createDomain();

        $repository = $this->createStub(DomainRepository::class);
        $repository->method('getDomainsForRegularProcessing')->willReturn([$domain]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $command = new ProcessDomainBulkCommand($repository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessDomainBulkCommandNoDomains(): void
    {
        $repository = $this->createStub(DomainRepository::class);
        $repository->method('getDomainsForRegularProcessing')->willReturn([]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $command = new ProcessDomainBulkCommand($repository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessDomainBulkCommandRespectsLimitOption(): void
    {
        $firstDomain = $this->createDomain();
        $secondDomain = $this->createDomain();

        $repository = $this->createStub(DomainRepository::class);
        $repository->method('getDomainsForRegularProcessing')->willReturn([$firstDomain, $secondDomain]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (ProcessDomain $message) use ($firstDomain): bool {
                return $message->domainId === $firstDomain->getId()->toRfc4122();
            }))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $command = new ProcessDomainBulkCommand($repository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute(['--limit' => '1']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessDomainBulkCommandSkipsDomainsWithoutId(): void
    {
        $user = new User();
        $user->setEmail('bulk-skip@example.com');
        $user->setName('Bulk Skip');
        $user->setSharedPostboxIdentifierToken('bulk-skip-token');

        $domain = new Domain($user, 'example.com');

        $repository = $this->createStub(DomainRepository::class);
        $repository->method('getDomainsForRegularProcessing')->willReturn([$domain]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $command = new ProcessDomainBulkCommand($repository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessReportCommandDispatches(): void
    {
        $report = $this->createReport();
        $repository = self::getContainer()->get(ReportRepository::class);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (ProcessReport $message) use ($report): bool {
                return $message->reportId === $report->getId()->toRfc4122();
            }))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $command = new ProcessReportCommand($repository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute(['id' => $report->getId()->toRfc4122()]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessReportCommandPromptsForId(): void
    {
        $report = $this->createReport();

        $repository = $this->createStub(ReportRepository::class);
        $repository->method('find')->willReturn($report);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (ProcessReport $message) use ($report): bool {
                return $message->reportId === $report->getId()->toRfc4122();
            }))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $command = new ProcessReportCommand($repository, $messageBus);

        $tester = new CommandTester($command);
        $tester->setInputs([$report->getId()->toRfc4122()]);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessReportCommandFailsWhenMissing(): void
    {
        $repository = self::getContainer()->get(ReportRepository::class);
        $command = new ProcessReportCommand($repository, $this->createStub(MessageBusInterface::class));

        $tester = new CommandTester($command);
        $tester->execute(['id' => Uuid::v4()->toRfc4122()]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testProcessReportCommandFailsWhenReportIdIsMissing(): void
    {
        $repository = $this->createStub(ReportRepository::class);
        $repository->method('find')->willReturn(new Report());

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $command = new ProcessReportCommand($repository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute(['id' => 'existing-report-without-id']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testProcessReportBulkCommandDispatches(): void
    {
        $repository = self::getContainer()->get(ReportRepository::class);
        $this->createReport(isProcessed: false);
        $this->createReport(isProcessed: false);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $command = new ProcessReportBulkCommand($repository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessReportBulkCommandNoReports(): void
    {
        $repository = self::getContainer()->get(ReportRepository::class);
        $this->entityManager->getConnection()->executeStatement('DELETE FROM report');
        $this->entityManager->getConnection()->executeStatement('DELETE FROM email');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $command = new ProcessReportBulkCommand($repository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testCommandDefinitionsAreConfigured(): void
    {
        $processDomain = new ProcessDomainCommand(
            self::getContainer()->get(DomainRepository::class),
            self::getContainer()->get(MessageBusInterface::class),
        );
        $processDomainBulk = new ProcessDomainBulkCommand(
            self::getContainer()->get(DomainRepository::class),
            self::getContainer()->get(MessageBusInterface::class),
        );
        $processReport = new ProcessReportCommand(
            self::getContainer()->get(ReportRepository::class),
            self::getContainer()->get(MessageBusInterface::class),
        );

        self::assertTrue($processDomain->getDefinition()->hasArgument('id'));
        self::assertTrue($processDomainBulk->getDefinition()->hasOption('limit'));
        self::assertTrue($processReport->getDefinition()->hasArgument('id'));
    }

    private function createDomain(): Domain
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setSharedPostboxIdentifierToken('token');

        $domain = new Domain($user, 'example.com');
        $this->setEntityId($domain, Uuid::v4());

        return $domain;
    }

    private function createReport(bool $isProcessed = true): Report
    {
        $user = $this->createVerifiedUser('report-cmd-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $email = new Email();
        $email
            ->setCreatedAt(new \DateTimeImmutable('-1 day'))
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
            ->setIsProcessed($isProcessed)
            ->setBeginDate(new \DateTimeImmutable('-2 days'))
            ->setEndDate(new \DateTimeImmutable('-1 day'))
            ->setDomain('example.com')
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

    private function setEntityId(object $entity, Uuid $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
