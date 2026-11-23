<?php

declare(strict_types=1);

namespace App\Tests\Api\Intake;

use App\Entity\Intake\Mailbox;
use App\Message\Intake\PollMailbox;
use App\Repository\Intake\MailboxRepository;
use App\Service\Intake\MailboxCredentials;
use App\Tests\Api\ApiIntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 *
 * @coversNothing
 */
final class MailboxApiTest extends ApiIntegrationTestCase
{
    private const array VALID = [
        'host' => 'IMAP.Example.com',
        'name' => 'Reports',
        'password' => 'correct horse battery staple',
        'reportAddress' => 'dmarc@example.com',
        'username' => 'dmarc@example.com',
    ];

    private string $token;

    /**
     * @var list<object>
     */
    private array $dispatched = [];

    protected function setUp(): void
    {
        parent::setUp();

        // A queued read is recorded, not run: reading is the poller's test.
        $this->dispatched = [];
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            $this->dispatched[] = $message;

            return new Envelope($message);
        });
        self::getContainer()->set(MessageBusInterface::class, $bus);

        $this->entityManager->createQuery('DELETE FROM '.Mailbox::class)->execute();
        $email = 'mailboxes-'.uniqid('', true).'@example.com';
        $this->createAccount($email, 'Str0ngPassw0rd!@#');
        $this->token = $this->login($email, 'Str0ngPassw0rd!@#');
    }

    public function testAddsAMailboxAndNeverShowsItsPassword(): void
    {
        $created = $this->call('POST', '/v1/platform/intake/mailboxes', self::VALID);

        self::assertSame(201, $created['status'], json_encode($created['body'], JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('horse', json_encode($created['body'], JSON_THROW_ON_ERROR));
        self::assertSame('imap.example.com', $created['body']['host']);
        self::assertSame(993, $created['body']['port']);
        self::assertSame('tls', $created['body']['security']);
        self::assertSame('INBOX', $created['body']['folder']);
        self::assertSame('mark_seen', $created['body']['afterImport']);
        self::assertFalse($created['body']['passwordUnreadable']);

        $this->entityManager->clear();
        $mailbox = self::getContainer()->get(MailboxRepository::class)->find($created['body']['id']);
        self::assertInstanceOf(Mailbox::class, $mailbox);
        self::assertStringNotContainsString('horse', $mailbox->getEncryptedCredentials());
        self::assertSame('correct horse battery staple', self::getContainer()->get(MailboxCredentials::class)->password($mailbox));

        self::assertCount(1, $this->call('GET', '/v1/platform/intake/mailboxes')['body']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidMailboxProvider(): iterable
    {
        yield 'no password' => [['password' => null]];
        yield 'no host' => [['host' => '']];
        yield 'a host that is no hostname' => [['host' => 'imap server']];
        yield 'plain text' => [['security' => 'none']];
        yield 'a wildcard folder' => [['folder' => 'INBOX*']];
        yield 'a folder with a line break' => [['folder' => "INBOX\r\nA1 DELETE INBOX"]];
        yield 'a username with a line break' => [['username' => "dmarc\r\nA1 LOGOUT"]];
        yield 'a password with a line break' => [['password' => "secret\r\nA1 LOGOUT"]];
        yield 'moving without a target' => [['afterImport' => 'move']];
        yield 'moving into the same folder' => [['afterImport' => 'move', 'folder' => 'Reports', 'moveToFolder' => 'Reports']];
        yield 'a report address that is no address' => [['reportAddress' => 'dmarc']];
        yield 'reading too often' => [['pollIntervalMinutes' => 1]];
    }

    /**
     * @param array<string, mixed> $change
     */
    #[DataProvider('invalidMailboxProvider')]
    public function testRefusesAnInvalidMailbox(array $change): void
    {
        $response = $this->call('POST', '/v1/platform/intake/mailboxes', array_filter([...self::VALID, ...$change], static fn (mixed $value): bool => null !== $value));

        self::assertSame(422, $response['status'], json_encode($response['body'], JSON_THROW_ON_ERROR));
        self::assertCount(0, self::getContainer()->get(MailboxRepository::class)->findAll());
    }

    public function testChangesAMailboxAndStartsOverOnAnotherServer(): void
    {
        $id = $this->call('POST', '/v1/platform/intake/mailboxes', self::VALID)['body']['id'];
        $mailbox = self::getContainer()->get(MailboxRepository::class)->find($id);
        self::assertInstanceOf(Mailbox::class, $mailbox);
        $mailbox->setCursor(12345, 678);
        $this->entityManager->flush();

        $changed = $this->call('PATCH', '/v1/platform/intake/mailboxes/'.$id, ['security' => 'starttls', 'afterImport' => 'move', 'moveToFolder' => 'DMARC/Done']);
        self::assertSame(200, $changed['status']);
        self::assertSame(143, $changed['body']['port']);
        self::assertSame('DMARC/Done', $changed['body']['moveToFolder']);

        $this->entityManager->clear();
        self::assertSame(678, self::getContainer()->get(MailboxRepository::class)->find($id)?->getLastUid());

        $this->call('PATCH', '/v1/platform/intake/mailboxes/'.$id, ['host' => 'imap.other.example']);
        $this->entityManager->clear();
        self::assertNull(self::getContainer()->get(MailboxRepository::class)->find($id)?->getLastUid());
    }

    public function testATestThatCannotConnectSaysWhy(): void
    {
        $id = $this->call('POST', '/v1/platform/intake/mailboxes', [...self::VALID, 'host' => '127.0.0.1', 'port' => 9])['body']['id'];

        $result = $this->call('POST', '/v1/platform/intake/mailboxes/'.$id.'/test');

        self::assertSame(200, $result['status']);
        self::assertFalse($result['body']['ok']);
        self::assertNotEmpty($result['body']['error']);
        self::assertStringNotContainsString('horse', (string) $result['body']['error']);
    }

    public function testQueuesAReadAndRemovesAMailbox(): void
    {
        $id = $this->call('POST', '/v1/platform/intake/mailboxes', self::VALID)['body']['id'];

        self::assertSame(202, $this->call('POST', '/v1/platform/intake/mailboxes/'.$id.'/poll')['status']);
        self::assertEquals([new PollMailbox($id)], $this->dispatched);

        self::assertSame([], $this->call('GET', '/v1/platform/intake/mailboxes/'.$id.'/log')['body']);
        self::assertSame(204, $this->call('DELETE', '/v1/platform/intake/mailboxes/'.$id)['status']);
        self::assertSame([], $this->call('GET', '/v1/platform/intake/mailboxes')['body']);
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array{status: int, body: mixed}
     */
    private function call(string $method, string $uri, ?array $payload = null): array
    {
        $response = $this->requestJson(method: $method, uri: $uri, payload: $payload, headers: $this->authorizeHeaders($this->token));
        $content = (string) $response->getContent();

        return [
            'body' => '' === $content ? null : json_decode($content, true, 512, JSON_THROW_ON_ERROR),
            'status' => $response->getStatusCode(),
        ];
    }
}
