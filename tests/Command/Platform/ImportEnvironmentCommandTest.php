<?php

declare(strict_types=1);

namespace App\Tests\Command\Platform;

use App\Command\Platform\ImportEnvironmentCommand;
use App\Entity\Platform\PlatformSettings;
use App\Repository\Platform\PlatformSettingsRepository;
use App\Service\Platform\EnvironmentImporter;
use App\Service\Platform\LegacyEnvironment;
use App\Service\Platform\PlatformSettingsProvider;
use App\Service\Security\SecretBox;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ImportEnvironmentCommand::class)]
final class ImportEnvironmentCommandTest extends TestCase
{
    public function testReportsWhatTheInstallerMayRemoveFromDotenv(): void
    {
        $tester = new CommandTester($this->command(str_repeat('ab', 32), [
            'APP_REPORT_RETENTION_DAYS' => '30',
            'MAILER_DSN' => 'sendmail://default',
        ]));
        $tester->execute(['--format' => 'json']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            'imported' => ['APP_REPORT_RETENTION_DAYS'],
            'invalid' => ['MAILER_DSN'],
            'kept' => [],
            'mode' => 'platform',
            'settled' => ['APP_REPORT_RETENTION_DAYS'],
        ], json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testSaysWhenTheEnvironmentStillAnswers(): void
    {
        $tester = new CommandTester($this->command(null, ['APP_REPORT_RETENTION_DAYS' => '30']));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Run the installer again', $tester->getDisplay());
    }

    public function testNamesWhatItTookOver(): void
    {
        $tester = new CommandTester($this->command(str_repeat('ab', 32), ['APP_IP_LOOKUP_ENABLED' => 'false']));
        $tester->execute([]);

        self::assertStringContainsString('Took APP_IP_LOOKUP_ENABLED over into the database.', $tester->getDisplay());
        self::assertStringContainsString('Those lines can go from .env.', $tester->getDisplay());
    }

    /**
     * @param array<string, string> $environment
     */
    private function command(?string $key, array $environment): ImportEnvironmentCommand
    {
        $repository = $this->createStub(PlatformSettingsRepository::class);
        $repository->method('get')->willReturn(new PlatformSettings('token'));

        $legacyEnvironment = new LegacyEnvironment($environment);
        $provider = new PlatformSettingsProvider($repository, new SecretBox($key), $legacyEnvironment, 'https://dmarc.example.test');

        return new ImportEnvironmentCommand(new EnvironmentImporter($repository, $provider, $legacyEnvironment, new NullLogger()));
    }
}
