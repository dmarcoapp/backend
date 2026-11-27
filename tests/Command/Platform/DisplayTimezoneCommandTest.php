<?php

declare(strict_types=1);

namespace App\Tests\Command\Platform;

use App\Command\Platform\DisplayTimezoneCommand;
use App\Entity\Platform\PlatformSettings;
use App\Repository\Platform\PlatformSettingsRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(DisplayTimezoneCommand::class)]
final class DisplayTimezoneCommandTest extends TestCase
{
    public function testPrintsUtcUntilAZoneIsSet(): void
    {
        $tester = new CommandTester($this->command(new PlatformSettings('token')));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame("UTC\n", $tester->getDisplay());
    }

    public function testSetsTheZoneSpelledAsPhpSpellsIt(): void
    {
        $settings = new PlatformSettings('token');
        $repository = $this->createMock(PlatformSettingsRepository::class);
        $repository->method('get')->willReturn($settings);
        $repository->expects(self::once())->method('save')->with($settings);

        $tester = new CommandTester(new DisplayTimezoneCommand($repository));
        $tester->execute(['timezone' => ' europe/budapest ']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame('Europe/Budapest', $settings->getDisplayTimezone());
        self::assertStringContainsString('Europe/Budapest', $tester->getDisplay());

        $tester->execute([]);
        self::assertSame("Europe/Budapest\n", $tester->getDisplay());
    }

    public function testRefusesWhatIsNoZone(): void
    {
        $settings = new PlatformSettings('token');
        $settings->setDisplayTimezone('Europe/Budapest');
        $repository = $this->createMock(PlatformSettingsRepository::class);
        $repository->method('get')->willReturn($settings);
        $repository->expects(self::never())->method('save');

        $tester = new CommandTester(new DisplayTimezoneCommand($repository));
        $tester->execute(['timezone' => 'Budapest']);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertSame('Europe/Budapest', $settings->getDisplayTimezone());
    }

    private function command(PlatformSettings $settings): DisplayTimezoneCommand
    {
        $repository = $this->createStub(PlatformSettingsRepository::class);
        $repository->method('get')->willReturn($settings);

        return new DisplayTimezoneCommand($repository);
    }
}
