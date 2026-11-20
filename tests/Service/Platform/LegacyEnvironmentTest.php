<?php

declare(strict_types=1);

namespace App\Tests\Service\Platform;

use App\Service\Platform\LegacyEnvironment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LegacyEnvironment::class)]
final class LegacyEnvironmentTest extends TestCase
{
    public function testReadsAValueWhereverItCameFrom(): void
    {
        $environment = new LegacyEnvironment([
            'MAILER_DSN' => ' smtp://mail.example.com ',
            'APP_REPORT_RETENTION_DAYS' => '30',
            'SYMFONY_DOTENV_VARS' => 'APP_REPORT_RETENTION_DAYS',
        ]);

        self::assertSame('smtp://mail.example.com', $environment->get('MAILER_DSN'));
        self::assertSame('30', $environment->get('APP_REPORT_RETENTION_DAYS'));
        self::assertNull($environment->get('APP_IP_LOOKUP_ENABLED'));
    }

    public function testADefaultFromDotenvIsNotTheOperators(): void
    {
        $environment = new LegacyEnvironment([
            'APP_EMAIL_SENDER_ADDRESS' => 'no-reply@example.com',
            'APP_REPORT_RETENTION_DAYS' => '90',
            'MAILER_DSN' => '',
            'SYMFONY_DOTENV_VARS' => 'APP_EMAIL_SENDER_ADDRESS,APP_SECRET',
        ]);

        self::assertNull($environment->getFromOperator('APP_EMAIL_SENDER_ADDRESS'));
        self::assertSame('90', $environment->getFromOperator('APP_REPORT_RETENTION_DAYS'));
        self::assertNull($environment->getFromOperator('MAILER_DSN'));
        self::assertSame(['APP_REPORT_RETENTION_DAYS'], $environment->presentSettings());
    }

    public function testReadsTheProcessEnvironmentByDefault(): void
    {
        $_SERVER['APP_DMARC_AGGREGATE_REPORT_EMAIL_RECEIVER_DOMAIN_PROBE'] = 'reports.example.com';

        try {
            self::assertSame('reports.example.com', new LegacyEnvironment()->get('APP_DMARC_AGGREGATE_REPORT_EMAIL_RECEIVER_DOMAIN_PROBE'));
        } finally {
            unset($_SERVER['APP_DMARC_AGGREGATE_REPORT_EMAIL_RECEIVER_DOMAIN_PROBE']);
        }
    }
}
