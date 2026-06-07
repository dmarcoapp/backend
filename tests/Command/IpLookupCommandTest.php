<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\Utils\IpLookupCommand;
use App\Service\IpLookup\IpLookupInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(IpLookupCommand::class)]
final class IpLookupCommandTest extends TestCase
{
    public function testLookupCommandOutputsResults(): void
    {
        $ipLookup = $this->createStub(IpLookupInterface::class);
        $ipLookup->method('lookup')->willReturn([
            'name' => 'Example',
            'country' => 'HU',
            'email' => [
                'abuse' => 'abuse@example.com',
                'tech' => 'tech@example.com',
            ],
        ]);

        $command = new IpLookupCommand($ipLookup);

        $tester = new CommandTester($command);
        $tester->execute(['ip' => '192.0.2.1']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Lookup results', $tester->getDisplay());
    }

    public function testLookupCommandFailsOnError(): void
    {
        $ipLookup = $this->createStub(IpLookupInterface::class);
        $ipLookup->method('lookup')->willThrowException(new \RuntimeException('fail'));

        $command = new IpLookupCommand($ipLookup);

        $tester = new CommandTester($command);
        $tester->execute(['ip' => '192.0.2.1']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('fail', $tester->getDisplay());
    }
}
