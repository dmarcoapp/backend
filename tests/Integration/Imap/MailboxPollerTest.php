<?php

declare(strict_types=1);

namespace App\Tests\Integration\Imap;

use App\DTO\Input\Intake\MailboxInput;
use App\Entity\Email\Email;
use App\Entity\Intake\Mailbox;
use App\Entity\Intake\MailboxLogEntry;
use App\Enum\Email\ReportSource;
use App\Repository\Intake\MailboxRepository;
use App\Service\Intake\MailboxEditor;
use App\Service\Intake\MailboxPoller;
use App\Service\Intake\MailboxTester;
use App\Tests\Api\ApiIntegrationTestCase;
use DirectoryTree\ImapEngine\Mailbox as ImapMailbox;
use DirectoryTree\ImapEngine\MessageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Reads a real IMAP server, GreenMail, over TLS with a certificate checked
 * against a test CA. Skipped unless the server is there, see
 * start-greenmail.sh beside this file.
 *
 * @internal
 */
#[CoversClass(MailboxPoller::class)]
#[CoversClass(MailboxTester::class)]
#[Group('imap')]
final class MailboxPollerTest extends ApiIntegrationTestCase
{
    private const string PASSWORD = 'imap-test-password';

    private string $username;

    protected function setUp(): void
    {
        parent::setUp();

        if (false === getenv('IMAP_TEST_HOST')) {
            self::markTestSkipped('No test IMAP server. Start one with tests/Integration/Imap/start-greenmail.sh.');
        }

        // The report is stored, and processing it is not this test's business.
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));
        self::getContainer()->set(MessageBusInterface::class, $bus);

        $this->removeReportData();
        $this->username = 'dmarc-'.bin2hex(random_bytes(6)).'@example.test';
        HttpClient::create()->request('POST', self::api().'/api/user', [
            'json' => ['email' => $this->username, 'login' => $this->username, 'password' => self::PASSWORD],
        ])->getStatusCode();
    }

    public function testStoresAReportAndLeavesOtherMailAlone(): void
    {
        $this->append($this->reportEmail('first'));
        $this->append($this->ordinaryEmail());
        $mailbox = $this->mailbox();

        $result = $this->poller()->poll($mailbox);

        self::assertNull($result->error);
        self::assertSame(2, $result->looked);
        self::assertSame(1, $result->imported);
        self::assertSame(1, $result->skipped);

        $emails = $this->entityManager->getRepository(Email::class)->findBy(['source' => ReportSource::IMAP]);
        self::assertCount(1, $emails);
        self::assertSame('noreply-dmarc-support@google.com', $emails[0]->getFromAddress());
        self::assertSame('dmarc@example.test', $emails[0]->getToAddress());

        $messages = $this->messages('INBOX');
        self::assertTrue($messages['report-first']->isSeen());
        self::assertFalse($messages['ordinary']->isSeen());

        // The next read starts where this one stopped.
        $again = $this->poller()->poll($mailbox);
        self::assertSame(0, $again->looked);
        self::assertNotNull($mailbox->getLastSuccessAt());
    }

    public function testLeavingChangesNothing(): void
    {
        $this->append($this->reportEmail('leave'));

        $result = $this->poller()->poll($this->mailbox(new MailboxInput(afterImport: 'leave')));

        self::assertSame(1, $result->imported);
        self::assertFalse($this->messages('INBOX')['report-leave']->isSeen());
    }

    public function testMovesTheReportAndNothingElse(): void
    {
        $this->client()->folders()->create('DMARC-Done');
        $this->append($this->reportEmail('move'));
        $this->append($this->ordinaryEmail());

        $result = $this->poller()->poll($this->mailbox(new MailboxInput(afterImport: 'move', moveToFolder: 'DMARC-Done')));

        self::assertSame(1, $result->imported);
        self::assertSame(['ordinary'], array_keys($this->messages('INBOX')));
        self::assertSame(['report-move'], array_keys($this->messages('DMARC-Done')));
    }

    public function testDeletingNeverExpungesTheUsersOwnMail(): void
    {
        // Mail the user marked deleted and has not expunged yet.
        $this->append($this->ordinaryEmail(), ['\\Deleted']);
        $this->append($this->reportEmail('delete'));

        $result = $this->poller()->poll($this->mailbox(new MailboxInput(afterImport: 'delete')));

        self::assertSame(1, $result->imported);
        $messages = $this->messages('INBOX');
        self::assertSame(['ordinary', 'report-delete'], array_keys($messages));
        self::assertTrue($messages['report-delete']->isDeleted());
    }

    public function testLeavesAZipBombWhereItIsAndSaysSo(): void
    {
        $this->append($this->reportEmail('bomb', $this->zip(str_repeat(' ', 9 * 1024 * 1024)), 'application/zip', 'bomb.zip'));
        $mailbox = $this->mailbox();

        $result = $this->poller()->poll($mailbox);

        self::assertSame(0, $result->imported);
        self::assertSame(1, $result->skipped);
        self::assertFalse($this->messages('INBOX')['report-bomb']->isSeen());

        $entries = $this->entityManager->getRepository(MailboxLogEntry::class)->findBy(['mailbox' => $mailbox]);
        self::assertStringContainsString('size limit', $entries[0]->getMessage());
    }

    public function testRecordsAPasswordTheServerRefuses(): void
    {
        $mailbox = $this->mailbox(password: 'wrong');

        $result = $this->poller()->poll($mailbox);

        self::assertNotNull($result->error);
        self::assertStringNotContainsString('wrong', $result->error);
        self::assertSame(1, $mailbox->getConsecutiveFailures());
        self::assertFalse(self::getContainer()->get(MailboxTester::class)->test($mailbox)->ok);
    }

    public function testATestSeesTheFolders(): void
    {
        $result = self::getContainer()->get(MailboxTester::class)->test($this->mailbox());

        self::assertTrue($result->ok, (string) $result->error);
        self::assertContains('INBOX', $result->folders);
    }

    private function mailbox(?MailboxInput $change = null, string $password = self::PASSWORD): Mailbox
    {
        $editor = self::getContainer()->get(MailboxEditor::class);
        $mailbox = $editor->create(new MailboxInput(
            name: 'Test '.bin2hex(random_bytes(3)),
            host: (string) getenv('IMAP_TEST_HOST'),
            port: (int) (getenv('IMAP_TEST_PORT') ?: 3993),
            security: 'tls',
            username: $this->username,
            password: $password,
            reportAddress: 'dmarc@example.test',
            firstImportDays: 1,
        ));
        if (null !== $change) {
            $editor->update($mailbox, $change);
        }
        self::getContainer()->get(MailboxRepository::class)->save($mailbox);

        return $mailbox;
    }

    private function poller(): MailboxPoller
    {
        return self::getContainer()->get(MailboxPoller::class);
    }

    private function client(): ImapMailbox
    {
        $client = new ImapMailbox([
            'encryption' => 'ssl',
            'host' => (string) getenv('IMAP_TEST_HOST'),
            'password' => self::PASSWORD,
            'port' => (int) (getenv('IMAP_TEST_PORT') ?: 3993),
            'username' => $this->username,
            'validate_cert' => true,
        ]);
        $client->connect();

        return $client;
    }

    /**
     * @param list<string> $flags
     */
    private function append(string $message, array $flags = []): void
    {
        $this->client()->inbox()->messages()->append($message, $flags);
    }

    /**
     * The messages of a folder, keyed by the subject they were given here.
     *
     * @return array<string, MessageInterface>
     */
    private function messages(string $folder): array
    {
        $found = $this->client()->folders()->find($folder);
        self::assertNotNull($found);

        $messages = [];
        foreach ($found->messages()->leaveUnread()->withHeaders()->withFlags()->get() as $message) {
            $messages[(string) $message->subject()] = $message;
        }
        ksort($messages);

        return $messages;
    }

    private function reportEmail(string $name, ?string $attachment = null, string $type = 'application/gzip', string $filename = 'google.com!example.com!1700000000!1700086400.xml.gz'): string
    {
        $attachment ??= (string) gzencode($this->reportXml($name));
        $encoded = chunk_split(base64_encode($attachment), 76, "\r\n");

        return implode("\r\n", [
            'From: Google <noreply-dmarc-support@google.com>',
            'To: dmarc@example.test',
            'Subject: report-'.$name,
            'Message-ID: <'.$name.'-'.bin2hex(random_bytes(4)).'@google.com>',
            'Date: '.date(DATE_RFC2822),
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="part"',
            '',
            '--part',
            'Content-Type: text/plain',
            '',
            'This is an aggregate report.',
            '--part',
            'Content-Type: '.$type.'; name="'.$filename.'"',
            'Content-Disposition: attachment; filename="'.$filename.'"',
            'Content-Transfer-Encoding: base64',
            '',
            $encoded.'--part--',
            '',
        ]);
    }

    private function ordinaryEmail(): string
    {
        return implode("\r\n", [
            'From: Colleague <colleague@example.test>',
            'To: dmarc@example.test',
            'Subject: ordinary',
            'Message-ID: <ordinary-'.bin2hex(random_bytes(4)).'@example.test>',
            'Date: '.date(DATE_RFC2822),
            '',
            'Lunch?',
            '',
        ]);
    }

    private function reportXml(string $id): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<feedback>
  <report_metadata>
    <org_name>google.com</org_name>
    <email>noreply-dmarc-support@google.com</email>
    <report_id>imap-{$id}-{$this->username}</report_id>
    <date_range><begin>1700000000</begin><end>1700086400</end></date_range>
  </report_metadata>
  <policy_published><domain>example.com</domain><adkim>r</adkim><aspf>r</aspf><p>none</p><sp>none</sp><pct>100</pct></policy_published>
  <record>
    <row><source_ip>192.0.2.1</source_ip><count>1</count><policy_evaluated><disposition>none</disposition><dkim>pass</dkim><spf>pass</spf></policy_evaluated></row>
    <identifiers><header_from>example.com</header_from></identifiers>
    <auth_results><dkim><domain>example.com</domain><result>pass</result></dkim><spf><domain>example.com</domain><result>pass</result></spf></auth_results>
  </record>
</feedback>
XML;
    }

    private function zip(string $content): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'dmarco-test-');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('report.xml', $content);
        $zip->close();
        $zipped = (string) file_get_contents($path);
        unlink($path);

        return $zipped;
    }

    private static function api(): string
    {
        return rtrim((string) (getenv('IMAP_TEST_API') ?: 'http://localhost:18080'), '/');
    }
}
