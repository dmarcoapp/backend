<?php

declare(strict_types=1);

namespace App\Service\Platform;

use App\Entity\Platform\PlatformSettings;
use App\Enum\Platform\SmtpSecurity;
use App\Repository\Platform\PlatformSettingsRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\Dsn;

/**
 * Takes the settings that used to live in the environment over into the
 * database, once. Only what the operator set counts, not a default committed
 * in .env, and a setting already made in the database is never overwritten.
 */
final readonly class EnvironmentImporter
{
    private const string IMPORTED = 'imported';
    private const string KEPT = 'kept';
    private const string INVALID = 'invalid';

    public function __construct(
        private PlatformSettingsRepository $repository,
        private PlatformSettingsProvider $provider,
        private LegacyEnvironment $environment,
        private LoggerInterface $logger,
    ) {}

    public function import(): EnvironmentImportResult
    {
        if ($this->provider->isLegacyMode()) {
            // The environment still answers, so there is nothing to take over yet.
            return new EnvironmentImportResult(legacyMode: true);
        }

        $settings = $this->repository->get();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $outcomes = [self::IMPORTED => [], self::KEPT => [], self::INVALID => []];

        foreach (LegacyEnvironment::SETTINGS as $name) {
            $value = $this->environment->getFromOperator($name);
            if (null === $value) {
                continue;
            }

            $outcome = match ($name) {
                LegacyEnvironment::WEBHOOK_SECRET => $this->importWebhookSecret($settings, $value),
                LegacyEnvironment::IP_LOOKUP_ENABLED => $this->importIpLookup($settings, $value),
                LegacyEnvironment::MAILER_DSN => $this->importMailerDsn($settings, $value),
                LegacyEnvironment::REPORT_DOMAIN => $this->importReportDomain($settings, $value),
                LegacyEnvironment::REPORT_RETENTION_DAYS => $this->importRetention($settings, $value),
                LegacyEnvironment::SENDER_ADDRESS => $this->importSender($settings, $value),
                default => self::INVALID,
            };

            if (self::IMPORTED === $outcome) {
                $settings->markImportedFromEnvironment($name, $now);
            }
            if (self::INVALID === $outcome) {
                $this->logger->warning('An environment setting could not be taken over.', ['variable' => $name]);
            }

            $outcomes[$outcome][] = $name;
        }

        $this->repository->save($settings);

        return new EnvironmentImportResult(
            legacyMode: false,
            imported: $outcomes[self::IMPORTED],
            kept: $outcomes[self::KEPT],
            invalid: $outcomes[self::INVALID],
        );
    }

    /**
     * A gateway with a secret was a gateway in use, so the API and its
     * webhook stay switched on.
     */
    private function importWebhookSecret(PlatformSettings $settings, string $value): string
    {
        if (null !== $settings->getEncryptedWebhookSecret()) {
            return self::KEPT;
        }

        $settings->setEncryptedWebhookSecret($this->provider->encryptWebhookSecret($value));
        if (null === $settings->isApiIntakeEnabled()) {
            $settings->setApiIntakeEnabled(true);
        }
        if (null === $settings->isWebhookEnabled()) {
            $settings->setWebhookEnabled(true);
        }

        return self::IMPORTED;
    }

    private function importIpLookup(PlatformSettings $settings, string $value): string
    {
        if (null !== $settings->isIpLookupEnabled()) {
            return self::KEPT;
        }

        $enabled = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if (null === $enabled) {
            return self::INVALID;
        }

        $settings->setIpLookupEnabled($enabled);

        return self::IMPORTED;
    }

    private function importRetention(PlatformSettings $settings, string $value): string
    {
        if (null !== $settings->getReportRetentionDays()) {
            return self::KEPT;
        }

        $days = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if (false === $days) {
            return self::INVALID;
        }

        $settings->setReportRetentionDays($days);

        return self::IMPORTED;
    }

    private function importSender(PlatformSettings $settings, string $value): string
    {
        if (null !== $settings->getSenderAddress()) {
            return self::KEPT;
        }

        if (false === filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return self::INVALID;
        }

        $settings->setSenderAddress($value);

        return self::IMPORTED;
    }

    private function importReportDomain(PlatformSettings $settings, string $value): string
    {
        if (null !== $settings->getReportAddressDomain()) {
            return self::KEPT;
        }

        $domain = strtolower($value);
        if (false === filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || !str_contains($domain, '.')) {
            return self::INVALID;
        }

        $settings->setReportAddressDomain($domain);

        return self::IMPORTED;
    }

    /**
     * An SMTP DSN becomes the mail server settings. The null transport is how
     * the installer said "no outgoing mail", which is the default anyway.
     */
    private function importMailerDsn(PlatformSettings $settings, string $value): string
    {
        if (null !== $settings->getSmtpHost()) {
            return self::KEPT;
        }

        try {
            $dsn = Dsn::fromString($value);
        } catch (\InvalidArgumentException) {
            return self::INVALID;
        }

        if ('null' === $dsn->getScheme()) {
            return self::IMPORTED;
        }

        if (!in_array($dsn->getScheme(), ['smtp', 'smtps'], true) || 'default' === $dsn->getHost()) {
            return self::INVALID;
        }

        $port = $dsn->getPort();
        $security = match (true) {
            'smtps' === $dsn->getScheme(), 465 === $port => SmtpSecurity::TLS,
            false === filter_var($dsn->getOption('auto_tls', true), FILTER_VALIDATE_BOOL) => SmtpSecurity::NONE,
            default => SmtpSecurity::STARTTLS,
        };

        $password = $dsn->getPassword();
        $settings
            ->setSmtpHost($dsn->getHost())
            ->setSmtpPort($port)
            ->setSmtpSecurity($security)
            ->setSmtpUsername($dsn->getUser())
            ->setEncryptedSmtpPassword(null === $password || '' === $password ? null : $this->provider->encryptSmtpPassword($password))
        ;

        return self::IMPORTED;
    }
}
