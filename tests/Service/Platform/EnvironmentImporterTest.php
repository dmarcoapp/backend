<?php

declare(strict_types=1);

namespace App\Tests\Service\Platform;

use App\Entity\Platform\PlatformSettings;
use App\Enum\Platform\SmtpSecurity;
use App\Repository\Platform\PlatformSettingsRepository;
use App\Service\Platform\EnvironmentImporter;
use App\Service\Platform\LegacyEnvironment;
use App\Service\Platform\PlatformSettingsProvider;
use App\Service\Security\SecretBox;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @internal
 */
#[CoversClass(EnvironmentImporter::class)]
final class EnvironmentImporterTest extends TestCase
{
    private const string KEY = '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f';

    public function testWithoutAKeyNothingIsTakenOver(): void
    {
        $settings = new PlatformSettings('token');
        [$importer] = $this->importer($settings, null, ['APP_REPORT_RETENTION_DAYS' => '30']);

        $result = $importer->import();

        self::assertTrue($result->legacyMode);
        self::assertSame([], $result->settled());
        self::assertSame([], $result->invalid);
        self::assertNull($settings->getReportRetentionDays());
    }

    public function testTakesEverySettingOver(): void
    {
        $settings = new PlatformSettings('token');
        [$importer, $provider] = $this->importer($settings, self::KEY, [
            'APP_DMARC_AGGREGATE_REPORT_EMAIL_RECEIVER_DOMAIN' => 'Dmarc.Example.com',
            'APP_EMAIL_SENDER_ADDRESS' => 'no-reply@dmarc.example.com',
            'APP_INBOUND_REPORT_EMAIL_WEBHOOK_SECRET' => 'gateway-secret',
            'APP_IP_LOOKUP_ENABLED' => 'false',
            'APP_REPORT_RETENTION_DAYS' => '30',
            'MAILER_DSN' => 'smtp://user%40example.com:p%40ss%2Fword@smtp.example.com:587',
        ]);

        $result = $importer->import();

        self::assertFalse($result->legacyMode);
        self::assertSame(LegacyEnvironment::SETTINGS, $result->imported);
        self::assertSame([], $result->invalid);
        self::assertSame('dmarc.example.com', $settings->getReportAddressDomain());
        self::assertSame('no-reply@dmarc.example.com', $settings->getSenderAddress());
        self::assertFalse($settings->isIpLookupEnabled());
        self::assertSame(30, $settings->getReportRetentionDays());
        self::assertSame('smtp.example.com', $settings->getSmtpHost());
        self::assertSame(587, $settings->getSmtpPort());
        self::assertSame(SmtpSecurity::STARTTLS, $settings->getSmtpSecurity());
        self::assertSame('user@example.com', $settings->getSmtpUsername());
        self::assertStringNotContainsString('word', (string) $settings->getEncryptedSmtpPassword());
        self::assertSame('p@ss/word', $provider->smtpPassword());
        self::assertSame('gateway-secret', $provider->gatewaySecret());
        self::assertTrue($settings->isApiIntakeEnabled());
        self::assertSame(LegacyEnvironment::SETTINGS, array_keys($settings->getImportedFromEnvironment()));
    }

    public function testNeverOverwritesASettingAlreadyMade(): void
    {
        $settings = new PlatformSettings('token')
            ->setReportRetentionDays(90)
            ->setSmtpHost('kept.example.com')
        ;
        [$importer] = $this->importer($settings, self::KEY, [
            'APP_REPORT_RETENTION_DAYS' => '30',
            'MAILER_DSN' => 'smtp://smtp.example.com',
        ]);

        $result = $importer->import();

        self::assertSame(['APP_REPORT_RETENTION_DAYS', 'MAILER_DSN'], $result->kept);
        self::assertSame(['APP_REPORT_RETENTION_DAYS', 'MAILER_DSN'], $result->settled());
        self::assertSame(90, $settings->getReportRetentionDays());
        self::assertSame('kept.example.com', $settings->getSmtpHost());
        self::assertSame([], $settings->getImportedFromEnvironment());
    }

    public function testIgnoresTheDefaultsCommittedInDotenv(): void
    {
        $settings = new PlatformSettings('token');
        [$importer] = $this->importer($settings, self::KEY, [
            'APP_EMAIL_SENDER_ADDRESS' => 'no-reply@example.com',
            'MAILER_DSN' => 'null://null',
            'SYMFONY_DOTENV_VARS' => 'APP_EMAIL_SENDER_ADDRESS,MAILER_DSN',
        ]);

        $result = $importer->import();

        self::assertSame([], $result->settled());
        self::assertNull($settings->getSenderAddress());
    }

    public function testTheNullTransportMeansNoOutgoingMail(): void
    {
        $settings = new PlatformSettings('token');
        [$importer] = $this->importer($settings, self::KEY, ['MAILER_DSN' => 'null://null']);

        self::assertSame(['MAILER_DSN'], $importer->import()->imported);
        self::assertNull($settings->getSmtpHost());
    }

    /**
     * @return iterable<string, array{string, SmtpSecurity, ?int}>
     */
    public static function smtpDsnProvider(): iterable
    {
        yield 'smtps' => ['smtps://smtp.example.com', SmtpSecurity::TLS, null];
        yield 'smtp on 465' => ['smtp://smtp.example.com:465', SmtpSecurity::TLS, 465];
        yield 'smtp without TLS' => ['smtp://relay:25?auto_tls=false', SmtpSecurity::NONE, 25];
        yield 'smtp' => ['smtp://smtp.example.com:2525', SmtpSecurity::STARTTLS, 2525];
    }

    #[DataProvider('smtpDsnProvider')]
    public function testReadsTheSecurityOfAnSmtpDsn(string $dsn, SmtpSecurity $security, ?int $port): void
    {
        $settings = new PlatformSettings('token');
        [$importer] = $this->importer($settings, self::KEY, ['MAILER_DSN' => $dsn]);

        $importer->import();

        self::assertSame($security, $settings->getSmtpSecurity());
        self::assertSame($port, $settings->getSmtpPort());
        self::assertNull($settings->getEncryptedSmtpPassword());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidValueProvider(): iterable
    {
        yield 'unparsable DSN' => ['MAILER_DSN', 'not a dsn'];
        yield 'transport that is not SMTP' => ['MAILER_DSN', 'sendmail://default'];
        yield 'negative retention' => ['APP_REPORT_RETENTION_DAYS', '-1'];
        yield 'retention that is no number' => ['APP_REPORT_RETENTION_DAYS', 'forever'];
        yield 'IP lookup that is no boolean' => ['APP_IP_LOOKUP_ENABLED', 'maybe'];
        yield 'sender that is no address' => ['APP_EMAIL_SENDER_ADDRESS', 'dmarco'];
        yield 'report domain that is no domain' => ['APP_DMARC_AGGREGATE_REPORT_EMAIL_RECEIVER_DOMAIN', 'not a domain'];
    }

    #[DataProvider('invalidValueProvider')]
    public function testLeavesAValueItCannotTakeOverInTheEnvironment(string $name, string $value): void
    {
        $settings = new PlatformSettings('token');
        [$importer] = $this->importer($settings, self::KEY, [$name => $value]);

        $result = $importer->import();

        self::assertSame([$name], $result->invalid);
        self::assertSame([], $result->settled());
        self::assertSame([], $settings->getImportedFromEnvironment());
    }

    /**
     * @param array<string, string> $environment
     *
     * @return array{EnvironmentImporter, PlatformSettingsProvider}
     */
    private function importer(PlatformSettings $settings, ?string $key, array $environment): array
    {
        $repository = $this->createStub(PlatformSettingsRepository::class);
        $repository->method('get')->willReturn($settings);

        $legacyEnvironment = new LegacyEnvironment($environment);
        $provider = new PlatformSettingsProvider($repository, new SecretBox($key), $legacyEnvironment, 'https://dmarc.example.test');

        return [new EnvironmentImporter($repository, $provider, $legacyEnvironment, new NullLogger()), $provider];
    }
}
