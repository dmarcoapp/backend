<?php

declare(strict_types=1);

namespace App\DTO\Output\Platform;

final class PlatformSettingsApi
{
    public const string MODE_LEGACY = 'legacy';
    public const string MODE_PLATFORM = 'platform';

    /**
     * Legacy while the settings still come from the environment, which is the
     * case until the installer has generated APP_ENCRYPTION_KEY.
     */
    public string $mode = self::MODE_PLATFORM;

    public int $reportRetentionDays = 0;
    public bool $ipLookupEnabled = true;

    public bool $mailConfigured = false;
    public ?string $smtpHost = null;
    public ?int $smtpPort = null;
    public ?string $smtpSecurity = null;
    public ?string $smtpUsername = null;
    public bool $smtpPasswordSet = false;

    /**
     * True when the stored password was encrypted with another key, so it has
     * to be entered again.
     */
    public bool $smtpPasswordUnreadable = false;

    /**
     * The address set on the Platform page, null while the default applies.
     */
    public ?string $senderAddress = null;
    public string $effectiveSenderAddress = '';
    public string $defaultSenderAddress = '';

    public ?string $reportAddress = null;
    public ?string $reportAddressDomain = null;

    public bool $apiIntakeEnabled = false;
    public bool $gatewaySecretSet = false;

    /**
     * Settings still set in the environment that nothing reads any more.
     *
     * @var list<string>
     */
    public array $unusedEnvironment = [];

    /**
     * @var array<string, string>
     */
    public array $importedFromEnvironment = [];
}
