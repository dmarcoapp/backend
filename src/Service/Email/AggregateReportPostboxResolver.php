<?php

declare(strict_types=1);

namespace App\Service\Email;

use App\Service\Platform\PlatformSettingsProvider;
use Symfony\Component\String\ByteString;

final readonly class AggregateReportPostboxResolver
{
    public function __construct(
        private PlatformSettingsProvider $settings,
    ) {}

    /**
     * The inverse of AggregateReportPostboxAddressProvider: the local part of
     * the report address is the installation's report address token.
     */
    public function isReportAddress(string $postboxAddress): bool
    {
        $address = trim($postboxAddress);

        if (!str_contains($address, '@')) {
            return false;
        }

        $identifier = new ByteString($address)->before('@')->toString();

        if ('' === $identifier) {
            return false;
        }

        return $identifier === $this->settings->reportAddressToken();
    }
}
