<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Platform\PlatformSettings;
use App\Service\Email\AggregateReportPostboxAddressProvider;
use App\Tests\Support\PlatformSettingsTestFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AggregateReportPostboxAddressProvider::class)]
final class AggregateReportPostboxAddressProviderTest extends TestCase
{
    public function testTheAddressIsTheTokenAtTheReportDomain(): void
    {
        $settings = new PlatformSettings('token123')->setReportAddressDomain('aggregate.example.test');

        $provider = new AggregateReportPostboxAddressProvider(PlatformSettingsTestFactory::provider($settings));

        self::assertSame('token123@aggregate.example.test', $provider->getAddress());
    }

    public function testThereIsNoAddressWithoutAReportDomain(): void
    {
        $provider = new AggregateReportPostboxAddressProvider(PlatformSettingsTestFactory::provider(new PlatformSettings('token123')));

        self::assertNull($provider->getAddress());
    }

    public function testWithoutAKeyTheReportDomainComesFromTheEnvironment(): void
    {
        $provider = new AggregateReportPostboxAddressProvider(PlatformSettingsTestFactory::provider(
            new PlatformSettings('token123'),
            key: null,
            environment: ['APP_DMARC_AGGREGATE_REPORT_EMAIL_RECEIVER_DOMAIN' => 'legacy.example.test'],
        ));

        self::assertSame('token123@legacy.example.test', $provider->getAddress());
    }
}
