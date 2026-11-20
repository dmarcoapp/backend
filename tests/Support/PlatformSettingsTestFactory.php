<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Platform\PlatformSettings;
use App\Entity\Intake\Mailbox;
use App\Repository\Intake\MailboxRepository;
use App\Repository\Platform\PlatformSettingsRepository;
use App\Service\Email\AggregateReportPostboxAddressProvider;
use App\Service\Intake\IntakeAddresses;
use App\Service\Platform\LegacyEnvironment;
use App\Service\Platform\PlatformSettingsProvider;
use App\Service\Security\SecretBox;
use PHPUnit\Framework\TestCase;

/**
 * Builds the settings provider over one in-memory row of settings, for the
 * tests of the services that read it.
 */
final class PlatformSettingsTestFactory
{
    public const string KEY = '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f';

    /**
     * @param array<string, string> $environment
     */
    public static function provider(
        ?PlatformSettings $settings = null,
        ?string $key = self::KEY,
        array $environment = [],
    ): PlatformSettingsProvider {
        // TestCase::createStub() is static but protected.
        $repository = new \ReflectionMethod(TestCase::class, 'createStub')->invoke(null, PlatformSettingsRepository::class);
        assert($repository instanceof PlatformSettingsRepository);
        $repository->method('get')->willReturn($settings ?? new PlatformSettings('reporttoken'));

        return new PlatformSettingsProvider($repository, new SecretBox($key), new LegacyEnvironment($environment), 'https://dmarc.example.test');
    }

    /**
     * The intake addresses: the gateway's, from these settings, and those of
     * the given mailboxes.
     *
     * @param list<Mailbox> $mailboxes
     */
    public static function intakeAddresses(?PlatformSettings $settings = null, array $mailboxes = []): IntakeAddresses
    {
        $provider = self::provider($settings);
        $repository = new \ReflectionMethod(TestCase::class, 'createStub')->invoke(null, MailboxRepository::class);
        assert($repository instanceof MailboxRepository);
        $repository->method('findEnabled')->willReturn($mailboxes);

        return new IntakeAddresses($provider, new AggregateReportPostboxAddressProvider($provider), $repository);
    }

    /**
     * Settings under which the gateway address is a report address.
     */
    public static function gatewaySettings(string $token, string $domain): PlatformSettings
    {
        return new PlatformSettings($token)->setReportAddressDomain($domain)->setApiIntakeEnabled(true);
    }
}
