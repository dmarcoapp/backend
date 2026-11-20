<?php

declare(strict_types=1);

namespace App\Tests\Service\Intake;

use App\Entity\Email\Email;
use App\Entity\User\BlocklistEntry;
use App\Enum\Email\ReportSource;
use App\Message\Email\ProcessEmail;
use App\Repository\Email\EmailRepository;
use App\Repository\User\BlocklistEntryRepository;
use App\Service\Intake\IncomingReport;
use App\Service\Intake\IngestOutcome;
use App\Service\Intake\ReportIngestor;
use App\Service\User\BlocklistMatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(ReportIngestor::class)]
final class ReportIngestorTest extends TestCase
{
    public function testStoresTheReportAndHandsItOn(): void
    {
        $saved = null;
        $emails = $this->createMock(EmailRepository::class);
        $emails->method('findOneBy')->willReturn(null);
        $emails->expects(self::once())->method('save')->willReturnCallback(static function (Email $email) use (&$saved): void {
            new \ReflectionProperty(Email::class, 'id')->setValue($email, Uuid::v7());
            $saved = $email;
        });

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(self::isInstanceOf(ProcessEmail::class))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $outcome = $this->ingestor($emails, $bus)->ingest($this->report(from: '"Google" <noreply-dmarc-support@google.com>', filename: 'google.com!example.com!1!2.xml.gz'));

        self::assertSame(IngestOutcome::ACCEPTED, $outcome);
        self::assertInstanceOf(Email::class, $saved);
        self::assertSame('noreply-dmarc-support@google.com', $saved->getFromAddress());
        self::assertSame('google.com!example.com!1!2.xml', $saved->getAttachmentFilename());
        self::assertSame('application/xml', $saved->getAttachmentContentType());
        self::assertSame(ReportSource::IMAP, $saved->getSource());
        self::assertSame('<feedback/>', $saved->getAttachmentContent());
    }

    public function testRecognizesAReportAlreadyReceived(): void
    {
        $emails = $this->createMock(EmailRepository::class);
        $emails->method('findOneBy')->with(['messageId' => '<id@example.com>', 'toAddress' => 'reports@example.com'])->willReturn(new Email());
        $emails->expects(self::never())->method('save');

        self::assertSame(IngestOutcome::ALREADY_RECEIVED, $this->ingestor($emails)->ingest($this->report()));
    }

    public function testLeavesOutABlockedSender(): void
    {
        $emails = $this->createMock(EmailRepository::class);
        $emails->method('findOneBy')->willReturn(null);
        $emails->expects(self::never())->method('save');

        self::assertSame(
            IngestOutcome::BLOCKED,
            $this->ingestor($emails, blocked: ['*@spam.example'])->ingest($this->report(from: 'someone@spam.example')),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function filenameProvider(): iterable
    {
        yield 'gzip' => ['report.xml.gz', 'report.xml'];
        yield 'zip without xml in the name' => ['google.com!example.com!1!2.zip', 'google.com!example.com!1!2.xml'];
        yield 'a path' => ['../../etc/passwd', 'passwd.xml'];
        yield 'a Windows path' => ['C:\\reports\\report.xml', 'report.xml'];
        yield 'nothing usable' => ['..', 'report.xml'];
        yield 'odd characters' => ["report<script>\n.xml", 'report_script__.xml'];
    }

    #[DataProvider('filenameProvider')]
    public function testNamesTheUnpackedReport(string $filename, string $expected): void
    {
        self::assertSame($expected, ReportIngestor::xmlFilename($filename));
    }

    /**
     * @param list<string> $blocked
     */
    private function ingestor(EmailRepository $emails, ?MessageBusInterface $bus = null, array $blocked = []): ReportIngestor
    {
        $blocklist = $this->createStub(BlocklistEntryRepository::class);
        $blocklist->method('getBlocklistEntries')->willReturn(array_map(static fn (string $pattern): BlocklistEntry => new BlocklistEntry($pattern), $blocked));

        return new ReportIngestor($emails, new BlocklistMatcher($blocklist), $bus ?? $this->createStub(MessageBusInterface::class), new NullLogger());
    }

    private function report(string $from = 'noreply@example.net', string $filename = 'report.xml'): IncomingReport
    {
        return new IncomingReport(
            source: ReportSource::IMAP,
            messageId: '<id@example.com>',
            fromAddress: $from,
            toAddress: 'reports@example.com',
            receivedAt: new \DateTimeImmutable('2026-11-01 10:00:00 UTC'),
            filename: $filename,
            xml: '<feedback/>',
        );
    }
}
