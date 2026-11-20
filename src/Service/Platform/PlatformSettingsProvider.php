<?php

declare(strict_types=1);

namespace App\Service\Platform;

use App\Entity\Platform\PlatformSettings;
use App\Enum\Platform\SmtpSecurity;
use App\Repository\Platform\PlatformSettingsRepository;
use App\Service\Security\SecretBox;
use App\Service\Security\UnreadableSecretException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Transport\Dsn;

/**
 * Where every setting that is not infrastructure is read from. The database
 * holds them, except until the installer has generated APP_ENCRYPTION_KEY: an
 * installation upgraded without it keeps reading the environment, as before.
 */
final readonly class PlatformSettingsProvider
{
    public const int DEFAULT_REPORT_RETENTION_DAYS = 365;

    public function __construct(
        private PlatformSettingsRepository $repository,
        private SecretBox $secretBox,
        private LegacyEnvironment $legacyEnvironment,
        #[Autowire(env: 'DEFAULT_URI')]
        private string $defaultUri,
    ) {}

    public function isLegacyMode(): bool
    {
        return !$this->secretBox->isConfigured();
    }

    public function settings(): PlatformSettings
    {
        return $this->repository->get();
    }

    public function reportRetentionDays(): int
    {
        $days = $this->isLegacyMode()
            ? filter_var($this->legacyEnvironment->get(LegacyEnvironment::REPORT_RETENTION_DAYS), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
            : $this->settings()->getReportRetentionDays();

        return null === $days ? self::DEFAULT_REPORT_RETENTION_DAYS : max(0, $days);
    }

    public function ipLookupEnabled(): bool
    {
        $enabled = $this->isLegacyMode()
            ? filter_var($this->legacyEnvironment->get(LegacyEnvironment::IP_LOOKUP_ENABLED), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
            : $this->settings()->isIpLookupEnabled();

        return $enabled ?? true;
    }

    public function reportAddressToken(): string
    {
        return $this->settings()->getReportAddressToken();
    }

    public function reportAddressDomain(): ?string
    {
        return $this->isLegacyMode()
            ? $this->legacyEnvironment->get(LegacyEnvironment::REPORT_DOMAIN)
            : $this->settings()->getReportAddressDomain();
    }

    public function senderAddress(): string
    {
        $address = $this->isLegacyMode()
            ? $this->legacyEnvironment->get(LegacyEnvironment::SENDER_ADDRESS)
            : $this->settings()->getSenderAddress();

        return $address ?? $this->defaultSenderAddress();
    }

    /**
     * no-reply@ the domain the dashboard is served on.
     */
    public function defaultSenderAddress(): string
    {
        $host = parse_url($this->defaultUri, PHP_URL_HOST);

        return 'no-reply@'.(is_string($host) && '' !== $host ? $host : 'localhost');
    }

    public function isMailConfigured(): bool
    {
        return 'null' !== $this->mailerDsn()->getScheme();
    }

    /**
     * @throws UnreadableSecretException when the stored password cannot be decrypted
     */
    public function mailerDsn(): Dsn
    {
        if ($this->isLegacyMode()) {
            return Dsn::fromString($this->legacyEnvironment->get(LegacyEnvironment::MAILER_DSN) ?? 'null://null');
        }

        $settings = $this->settings();
        $host = $settings->getSmtpHost();
        if (null === $host) {
            return new Dsn('null', 'null');
        }

        $security = $settings->getSmtpSecurity() ?? SmtpSecurity::STARTTLS;

        return new Dsn(
            SmtpSecurity::TLS === $security ? 'smtps' : 'smtp',
            $host,
            $settings->getSmtpUsername(),
            $this->smtpPassword(),
            $settings->getSmtpPort() ?? $security->defaultPort(),
            match ($security) {
                SmtpSecurity::TLS => [],
                SmtpSecurity::STARTTLS => ['require_tls' => 'true'],
                SmtpSecurity::NONE => ['auto_tls' => 'false'],
            },
        );
    }

    /**
     * @throws UnreadableSecretException
     */
    public function smtpPassword(): ?string
    {
        $encrypted = $this->settings()->getEncryptedSmtpPassword();

        return null === $encrypted ? null : $this->secretBox->decrypt($encrypted, PlatformSettings::SMTP_PASSWORD_CONTEXT);
    }

    public function isSmtpPasswordReadable(): bool
    {
        try {
            $this->smtpPassword();

            return true;
        } catch (UnreadableSecretException) {
            return false;
        }
    }

    /**
     * Before the settings moved into the database, the webhook was on whenever
     * the gateway had a secret, so it stays that way until the upgrade.
     */
    public function isApiIntakeEnabled(): bool
    {
        return $this->isLegacyMode()
            ? null !== $this->legacyEnvironment->get(LegacyEnvironment::GATEWAY_SECRET)
            : true === $this->settings()->isApiIntakeEnabled();
    }

    /**
     * The secret a mail gateway signs its webhooks with, or null without one.
     *
     * @throws UnreadableSecretException
     */
    public function gatewaySecret(): ?string
    {
        if ($this->isLegacyMode()) {
            return $this->legacyEnvironment->get(LegacyEnvironment::GATEWAY_SECRET);
        }

        $encrypted = $this->settings()->getEncryptedGatewaySecret();

        return null === $encrypted ? null : $this->secretBox->decrypt($encrypted, PlatformSettings::GATEWAY_SECRET_CONTEXT);
    }

    public function encryptGatewaySecret(#[\SensitiveParameter] string $secret): string
    {
        return $this->secretBox->encrypt($secret, PlatformSettings::GATEWAY_SECRET_CONTEXT);
    }

    public function encryptSmtpPassword(#[\SensitiveParameter] string $password): string
    {
        return $this->secretBox->encrypt($password, PlatformSettings::SMTP_PASSWORD_CONTEXT);
    }

    /**
     * The settings still set in the environment that nothing reads any more,
     * which the operator can remove from .env.
     *
     * @return list<string>
     */
    public function unusedEnvironment(): array
    {
        return $this->isLegacyMode() ? [] : $this->legacyEnvironment->presentSettings();
    }
}
