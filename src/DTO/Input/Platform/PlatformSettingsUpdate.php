<?php

declare(strict_types=1);

namespace App\DTO\Input\Platform;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * A change to the platform settings. A field left out stays as it is. An empty
 * string clears the field: the display time zone, which goes back to UTC, the
 * mail server, which turns outgoing mail off, the username or password, or the
 * sender address, which falls back to its default.
 */
final readonly class PlatformSettingsUpdate
{
    public function __construct(
        #[Assert\Range(min: 0, max: 3650)]
        public ?int $reportRetentionDays = null,
        #[Assert\Type(type: 'bool')]
        public ?bool $ipLookupEnabled = null,
        // An IANA time zone name. Empty goes back to UTC.
        #[Assert\Length(max: 64)]
        #[Assert\AtLeastOneOf([
            new Assert\Blank(),
            new Assert\Timezone(zone: \DateTimeZone::ALL_WITH_BC),
        ])]
        public ?string $displayTimezone = null,
        #[Assert\Length(max: 255)]
        #[Assert\AtLeastOneOf([
            new Assert\Blank(),
            new Assert\Hostname(requireTld: false),
            new Assert\Ip(version: Assert\Ip::ALL),
        ])]
        public ?string $smtpHost = null,
        #[Assert\Range(min: 1, max: 65535)]
        public ?int $smtpPort = null,
        #[Assert\Choice(choices: ['tls', 'starttls', 'none'])]
        public ?string $smtpSecurity = null,
        #[Assert\Length(max: 255)]
        public ?string $smtpUsername = null,
        #[Assert\Length(max: 1024)]
        public ?string $smtpPassword = null,
        #[Assert\Length(max: 255)]
        #[Assert\AtLeastOneOf([
            new Assert\Blank(),
            new Assert\Email(mode: Assert\Email::VALIDATION_MODE_STRICT),
        ])]
        public ?string $senderAddress = null,
        #[Assert\Type(type: 'bool')]
        public ?bool $apiIntakeEnabled = null,
        #[Assert\Type(type: 'bool')]
        public ?bool $uploadEndpointEnabled = null,
        #[Assert\Type(type: 'bool')]
        public ?bool $webhookEnabled = null,
        // The domain of the mail gateway's report address. Empty removes it.
        #[Assert\Length(max: 255)]
        #[Assert\AtLeastOneOf([
            new Assert\Blank(),
            new Assert\Hostname(),
        ])]
        public ?string $reportAddressDomain = null,
    ) {}
}
