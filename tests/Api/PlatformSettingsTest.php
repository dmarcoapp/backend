<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Platform\PlatformSettings;
use App\Repository\Platform\PlatformSettingsRepository;

/**
 * @internal
 *
 * @coversNothing
 */
final class PlatformSettingsTest extends ApiIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager->createQuery('DELETE FROM '.PlatformSettings::class)->execute();
        $this->entityManager->clear();
    }

    public function testShowsTheDefaultsOfAFreshInstallation(): void
    {
        $token = $this->signIn('platform-defaults@example.com');

        $payload = $this->getSettings($token);

        self::assertSame('platform', $payload['mode']);
        self::assertSame(365, $payload['reportRetentionDays']);
        self::assertTrue($payload['ipLookupEnabled']);
        self::assertFalse($payload['mailConfigured']);
        self::assertNull($payload['smtpHost']);
        self::assertNull($payload['senderAddress']);
        self::assertSame('no-reply@localhost', $payload['defaultSenderAddress']);
        self::assertSame('no-reply@localhost', $payload['effectiveSenderAddress']);
        self::assertNull($payload['reportAddress']);
    }

    public function testChangesTheSettingsAndNeverShowsThePassword(): void
    {
        $token = $this->signIn('platform-change@example.com');

        $response = $this->requestJson(
            method: 'PATCH',
            uri: '/v1/platform/settings',
            payload: [
                'ipLookupEnabled' => false,
                'reportRetentionDays' => 90,
                'senderAddress' => 'alerts@example.org',
                'smtpHost' => 'SMTP.Example.org',
                'smtpPassword' => 'correct horse battery staple',
                'smtpPort' => 465,
                'smtpSecurity' => 'tls',
                'smtpUsername' => 'alerts@example.org',
            ],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringNotContainsString('correct horse', (string) $response->getContent());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(90, $payload['reportRetentionDays']);
        self::assertFalse($payload['ipLookupEnabled']);
        self::assertTrue($payload['mailConfigured']);
        self::assertSame('smtp.example.org', $payload['smtpHost']);
        self::assertSame(465, $payload['smtpPort']);
        self::assertSame('tls', $payload['smtpSecurity']);
        self::assertSame('alerts@example.org', $payload['smtpUsername']);
        self::assertTrue($payload['smtpPasswordSet']);
        self::assertFalse($payload['smtpPasswordUnreadable']);
        self::assertSame('alerts@example.org', $payload['effectiveSenderAddress']);

        $this->entityManager->clear();
        $stored = self::getContainer()->get(PlatformSettingsRepository::class)->get();
        self::assertStringStartsWith('v1:', (string) $stored->getEncryptedSmtpPassword());
        self::assertStringNotContainsString('horse', (string) $stored->getEncryptedSmtpPassword());

        // A field left out stays, and an empty one is cleared.
        $response = $this->requestJson(
            method: 'PATCH',
            uri: '/v1/platform/settings',
            payload: ['senderAddress' => '', 'smtpPassword' => ''],
            headers: $this->authorizeHeaders($token),
        );

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(90, $payload['reportRetentionDays']);
        self::assertSame('smtp.example.org', $payload['smtpHost']);
        self::assertFalse($payload['smtpPasswordSet']);
        self::assertNull($payload['senderAddress']);
        self::assertSame('no-reply@localhost', $payload['effectiveSenderAddress']);

        // Without a host there is no mail server at all.
        $response = $this->requestJson(
            method: 'PATCH',
            uri: '/v1/platform/settings',
            payload: ['smtpHost' => ''],
            headers: $this->authorizeHeaders($token),
        );

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($payload['mailConfigured']);
        self::assertNull($payload['smtpUsername']);
        self::assertNull($payload['smtpSecurity']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidChangeProvider(): iterable
    {
        yield 'negative retention' => [['reportRetentionDays' => -1]];
        yield 'retention past ten years' => [['reportRetentionDays' => 3651]];
        yield 'host that is no hostname' => [['smtpHost' => 'mail server']];
        yield 'port out of range' => [['smtpPort' => 70000]];
        yield 'unknown security' => [['smtpSecurity' => 'ssl']];
        yield 'sender that is no address' => [['senderAddress' => 'dmarco']];
    }

    /**
     * @param array<string, mixed> $change
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidChangeProvider')]
    public function testRefusesAnInvalidChange(array $change): void
    {
        $token = $this->signIn('platform-invalid-'.md5(serialize($change)).'@example.com');

        $response = $this->requestJson(
            method: 'PATCH',
            uri: '/v1/platform/settings',
            payload: $change,
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testTheTestEmailSaysWhenOutgoingMailIsNotSetUp(): void
    {
        $token = $this->signIn('platform-test-email@example.com');

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/platform/mailer/test',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($payload['sent']);
        self::assertSame('platform-test-email@example.com', $payload['recipient']);
        self::assertSame('Outgoing mail is not set up.', $payload['error']);
    }

    public function testTheTestEmailReportsWhyTheServerRefused(): void
    {
        $token = $this->signIn('platform-test-refused@example.com');

        $this->requestJson(
            method: 'PATCH',
            uri: '/v1/platform/settings',
            payload: ['smtpHost' => '127.0.0.1', 'smtpPort' => 9, 'smtpSecurity' => 'none'],
            headers: $this->authorizeHeaders($token),
        );

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/platform/mailer/test',
            headers: $this->authorizeHeaders($token),
        );

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($payload['sent']);
        self::assertStringContainsString('127.0.0.1', (string) $payload['error']);
    }

    public function testTheSettingsNeedAnAccount(): void
    {
        $response = $this->requestJson(method: 'GET', uri: '/v1/platform/settings');

        self::assertSame(401, $response->getStatusCode());
    }

    private function signIn(string $email): string
    {
        $this->createAccount($email, 'Str0ngPassw0rd!@#');

        return $this->login($email, 'Str0ngPassw0rd!@#');
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(string $token): array
    {
        $response = $this->requestJson(
            method: 'GET',
            uri: '/v1/platform/settings',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
