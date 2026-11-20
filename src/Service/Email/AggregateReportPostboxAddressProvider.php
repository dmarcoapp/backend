<?php

declare(strict_types=1);

namespace App\Service\Email;

use App\Service\Platform\PlatformSettingsProvider;

final readonly class AggregateReportPostboxAddressProvider
{
    public function __construct(
        private PlatformSettingsProvider $settings,
    ) {}

    /**
     * The address reports are sent to, or null while there is no report domain.
     */
    public function getAddress(): ?string
    {
        $domain = $this->settings->reportAddressDomain();

        return null === $domain ? null : sprintf('%s@%s', $this->settings->reportAddressToken(), $domain);
    }
}
