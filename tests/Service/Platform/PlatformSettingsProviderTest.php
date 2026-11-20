<?php

declare(strict_types=1);

namespace App\Tests\Service\Platform;

use App\Entity\Platform\PlatformSettings;
use App\Enum\Platform\SmtpSecurity;
use App\Repository\Platform\PlatformSettingsRepository;
use App\Service\Platform\LegacyEnvironment;
use App\Service\Platform\PlatformSettingsProvider;
use App\Service\Security\SecretBox;
use App\Service\Security\UnreadableSecretException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PlatformSettingsProvider::class)]
final class PlatformSettingsProviderTest extends TestCase
{
    private const string KEY = '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f';

    public function testWithoutAKeyTheEnvironmentAnswersAsBefore(): void
    {
        $provider = $this->provider(new PlatformSettings('token'), null, [
            'APP_DMARC_AGGREGATE_REPORT_EMAIL_RECEIVER_DOMAIN' => 'reports.example.com',
            'APP_EMAIL_SENDER_ADDRESS' => 'dmarco@example.com',
            'APP_IP_LOOKUP_ENABLED' => 'false',
            'APP_REPORT_RETENTION_DAYS' => '30',
            'MAILER_DSN' => 'smtp://user:pass@mail.example.com:587',
        ]);

        self::assertTrue($provider->isLegacyMode());
        self::assertSame(30, $provider->reportRetentionDays());
        self::assertFalse($provider->ipLookupEnabled());
        self::assertSame('reports.example.com', $provider->reportAddressDomain());
        self::assertSame('dmarco@example.com', $provider->senderAddress());
        self::assertSame('mail.example.com', $provider->mailerDsn()->getHost());
        self::assertTrue($provider->isMailConfigured());
        self::assertSame([], $provider->unusedEnvironment());
    }

    public function testWithAKeyTheDatabaseAnswersAndTheEnvironmentIsIgnored(): void
    {
        $settings = new PlatformSettings('token')
            ->setReportRetentionDays(90)
            ->setIpLookupEnabled(false)
            ->setReportAddressDomain('dmarc.example.org')
            ->setSenderAddress('alerts@example.org')
        ;
        $provider = $this->provider($settings, self::KEY, [
            'APP_REPORT_RETENTION_DAYS' => '30',
            'MAILER_DSN' => 'smtp://mail.example.com',
        ]);

        self::assertFalse($provider->isLegacyMode());
        self::assertSame(90, $provider->reportRetentionDays());
        self::assertFalse($provider->ipLookupEnabled());
        self::assertSame('dmarc.example.org', $provider->reportAddressDomain());
        self::assertSame('alerts@example.org', $provider->senderAddress());
        self::assertFalse($provider->isMailConfigured());
        self::assertSame(['APP_REPORT_RETENTION_DAYS', 'MAILER_DSN'], $provider->unusedEnvironment());
    }

    public function testDefaultsApplyToSettingsNeverMade(): void
    {
        $provider = $this->provider(new PlatformSettings('token'), self::KEY, []);

        self::assertSame(365, $provider->reportRetentionDays());
        self::assertTrue($provider->ipLookupEnabled());
        self::assertNull($provider->reportAddressDomain());
        self::assertSame('no-reply@dmarc.example.test', $provider->senderAddress());
        self::assertSame('null', $provider->mailerDsn()->getScheme());
    }

    public function testBuildsTheSmtpDsnForEachKindOfSecurity(): void
    {
        $settings = new PlatformSettings('token')
            ->setSmtpHost('mail.example.com')
            ->setSmtpUsername('user@example.com')
        ;
        $provider = $this->provider($settings, self::KEY, []);
        $settings->setEncryptedSmtpPassword($provider->encryptSmtpPassword('p@ss/word'));

        $settings->setSmtpSecurity(SmtpSecurity::STARTTLS);
        $dsn = $provider->mailerDsn();
        self::assertSame('smtp', $dsn->getScheme());
        self::assertSame(587, $dsn->getPort());
        self::assertSame('user@example.com', $dsn->getUser());
        self::assertSame('p@ss/word', $dsn->getPassword());
        self::assertTrue($dsn->getBooleanOption('require_tls'));

        $settings->setSmtpSecurity(SmtpSecurity::TLS)->setSmtpPort(2465);
        $dsn = $provider->mailerDsn();
        self::assertSame('smtps', $dsn->getScheme());
        self::assertSame(2465, $dsn->getPort());

        $settings->setSmtpSecurity(SmtpSecurity::NONE)->setSmtpPort(null);
        $dsn = $provider->mailerDsn();
        self::assertSame('smtp', $dsn->getScheme());
        self::assertSame(25, $dsn->getPort());
        self::assertSame('false', $dsn->getOption('auto_tls'));
    }

    public function testAPasswordStoredWithAnotherKeyIsReportedAsUnreadable(): void
    {
        $settings = new PlatformSettings('token')
            ->setSmtpHost('mail.example.com')
            ->setEncryptedSmtpPassword(new SecretBox(str_repeat('ab', 32))->encrypt('secret', PlatformSettings::SMTP_PASSWORD_CONTEXT))
        ;
        $provider = $this->provider($settings, self::KEY, []);

        self::assertFalse($provider->isSmtpPasswordReadable());

        $this->expectException(UnreadableSecretException::class);
        $provider->mailerDsn();
    }

    /**
     * @param array<string, string> $environment
     */
    private function provider(PlatformSettings $settings, ?string $key, array $environment): PlatformSettingsProvider
    {
        $repository = $this->createStub(PlatformSettingsRepository::class);
        $repository->method('get')->willReturn($settings);

        return new PlatformSettingsProvider(
            $repository,
            new SecretBox($key),
            new LegacyEnvironment($environment),
            'https://dmarc.example.test',
        );
    }
}
