<?php

declare(strict_types=1);

namespace App\Tests\Api\Intake;

use App\Entity\Intake\ApiIntakeKey;
use App\Entity\Platform\PlatformSettings;
use App\Tests\Api\ApiIntegrationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class IntakeSettingsTest extends ApiIntegrationTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager->createQuery('DELETE FROM '.PlatformSettings::class)->execute();
        $this->entityManager->createQuery('DELETE FROM '.ApiIntakeKey::class)->execute();
        $email = 'intake-'.uniqid('', true).'@example.com';
        $this->createAccount($email, 'Str0ngPassw0rd!@#');
        $this->token = $this->login($email, 'Str0ngPassw0rd!@#');
    }

    public function testShowsAKeyOnceAndNeverAgain(): void
    {
        $created = $this->call('POST', '/v1/platform/intake/keys', ['name' => 'Forwarder']);
        self::assertSame(201, $created['status']);
        self::assertMatchesRegularExpression('/^dmarco_[a-z0-9]{16}_[A-Za-z0-9]{40}$/', (string) $created['body']['token']);

        $listed = $this->call('GET', '/v1/platform/intake/keys');
        self::assertCount(1, $listed['body']);
        self::assertSame('Forwarder', $listed['body'][0]['name']);
        self::assertNull($listed['body'][0]['token']);
        self::assertStringEndsWith('_…', $listed['body'][0]['preview']);
        self::assertStringNotContainsString(substr((string) $created['body']['token'], -40), json_encode($listed['body'], JSON_THROW_ON_ERROR));

        self::assertSame(204, $this->call('DELETE', '/v1/platform/intake/keys/'.$created['body']['id'])['status']);
        self::assertSame([], $this->call('GET', '/v1/platform/intake/keys')['body']);
    }

    public function testRefusesAKeyWithoutAName(): void
    {
        self::assertSame(422, $this->call('POST', '/v1/platform/intake/keys', ['name' => ''])['status']);
        self::assertSame(422, $this->call('POST', '/v1/platform/intake/keys', ['name' => "two\nlines"])['status']);
    }

    public function testMakesAndRemovesTheGatewaySecret(): void
    {
        $created = $this->call('POST', '/v1/platform/intake/gateway-secret');
        self::assertSame(201, $created['status']);
        self::assertSame(48, strlen((string) $created['body']['secret']));
        self::assertTrue($this->call('GET', '/v1/platform/settings')['body']['gatewaySecretSet']);

        self::assertSame(204, $this->call('DELETE', '/v1/platform/intake/gateway-secret')['status']);
        self::assertFalse($this->call('GET', '/v1/platform/settings')['body']['gatewaySecretSet']);
    }

    public function testListsTheAddressesReportsComeInAt(): void
    {
        self::assertSame([], $this->call('GET', '/v1/platform/intake/addresses')['body']);

        $changed = $this->call('PATCH', '/v1/platform/settings', ['apiIntakeEnabled' => true, 'reportAddressDomain' => 'Reports.Example.com']);
        self::assertSame(200, $changed['status']);
        self::assertTrue($changed['body']['apiIntakeEnabled']);
        self::assertSame('reports.example.com', $changed['body']['reportAddressDomain']);

        $addresses = $this->call('GET', '/v1/platform/intake/addresses')['body'];
        self::assertCount(1, $addresses);
        self::assertSame('gateway', $addresses[0]['kind']);
        self::assertStringEndsWith('@reports.example.com', $addresses[0]['address']);

        $this->call('POST', '/v1/platform/mailboxes', [
            'host' => 'imap.example.com',
            'name' => 'Reports',
            'password' => 'secret',
            'reportAddress' => 'dmarc@example.com',
            'username' => 'dmarc@example.com',
        ]);

        $addresses = $this->call('GET', '/v1/platform/intake/addresses')['body'];
        self::assertSame(['gateway', 'mailbox'], array_column($addresses, 'kind'));
        self::assertSame('dmarc@example.com', $addresses[1]['address']);
    }

    public function testRefusesAReportDomainThatIsNoDomain(): void
    {
        self::assertSame(422, $this->call('PATCH', '/v1/platform/settings', ['reportAddressDomain' => 'not a domain'])['status']);
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
