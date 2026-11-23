<?php

declare(strict_types=1);

namespace App\Service\Platform;

/**
 * The environment variables that carried settings before the settings moved
 * into the database. They are read while no encryption key is set, which keeps
 * an installation upgraded without the installer working as it did before, and
 * once, when the settings are taken over from them.
 */
final readonly class LegacyEnvironment
{
    public const string MAILER_DSN = 'MAILER_DSN';
    public const string SENDER_ADDRESS = 'APP_EMAIL_SENDER_ADDRESS';
    public const string REPORT_RETENTION_DAYS = 'APP_REPORT_RETENTION_DAYS';
    public const string IP_LOOKUP_ENABLED = 'APP_IP_LOOKUP_ENABLED';
    public const string REPORT_DOMAIN = 'APP_DMARC_AGGREGATE_REPORT_EMAIL_RECEIVER_DOMAIN';
    public const string WEBHOOK_SECRET = 'APP_INBOUND_REPORT_EMAIL_WEBHOOK_SECRET';

    // In the order of their names, which is the order every list of them
    // comes in.
    public const array SETTINGS = [
        self::REPORT_DOMAIN,
        self::SENDER_ADDRESS,
        self::WEBHOOK_SECRET,
        self::IP_LOOKUP_ENABLED,
        self::REPORT_RETENTION_DAYS,
        self::MAILER_DSN,
    ];

    /**
     * @var array<array-key, mixed>
     */
    private array $variables;

    /**
     * @param null|array<array-key, mixed> $variables the process environment when null
     */
    public function __construct(?array $variables = null)
    {
        $this->variables = $variables ?? [...$_ENV, ...$_SERVER];
    }

    /**
     * The value wherever it came from, the defaults committed in .env included,
     * which is how these settings were read before.
     */
    public function get(string $name): ?string
    {
        $value = $this->variables[$name] ?? null;
        if (!is_string($value) || '' === trim($value)) {
            return null;
        }

        return trim($value);
    }

    /**
     * The value only when the operator set it, rather than a default committed
     * in .env. Symfony lists the variables it loaded from those files.
     */
    public function getFromOperator(string $name): ?string
    {
        $loadedFromFiles = explode(',', (string) ($this->variables['SYMFONY_DOTENV_VARS'] ?? ''));

        return in_array($name, $loadedFromFiles, true) ? null : $this->get($name);
    }

    /**
     * The settings the operator still sets in the environment.
     *
     * @return list<string>
     */
    public function presentSettings(): array
    {
        return array_values(array_filter(
            self::SETTINGS,
            fn (string $name): bool => null !== $this->getFromOperator($name),
        ));
    }
}
