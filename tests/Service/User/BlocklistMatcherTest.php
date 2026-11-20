<?php

declare(strict_types=1);

namespace App\Tests\Service\User;

use App\Entity\User\BlocklistEntry;
use App\Repository\User\BlocklistEntryRepository;
use App\Service\User\BlocklistMatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BlocklistMatcher::class)]
final class BlocklistMatcherTest extends TestCase
{
    public function testMatchesGlobPatternsCaseInsensitive(): void
    {
        $entries = [
            new BlocklistEntry('valami@example.com'),
            new BlocklistEntry('*@example.com'),
            new BlocklistEntry('*.example.com'),
            new BlocklistEntry('*@valalmi.*'),
        ];

        $repository = $this->createStub(BlocklistEntryRepository::class);
        $repository->method('getBlocklistEntries')->willReturn($entries);

        $matcher = new BlocklistMatcher($repository);

        self::assertTrue($matcher->isBlocked('valami@example.com'));
        self::assertTrue($matcher->isBlocked('user@example.com'));
        self::assertTrue($matcher->isBlocked('user@a.example.com'));
        self::assertTrue($matcher->isBlocked('USER@VALALMI.HU'));
        self::assertFalse($matcher->isBlocked('user@example.org'));
    }

    public function testIsBlockedReturnsFalseForEmptyAddressAndSkipsEmptyPattern(): void
    {
        $entries = [
            new BlocklistEntry('   '),
            new BlocklistEntry('*@example.com'),
        ];

        $repository = $this->createStub(BlocklistEntryRepository::class);
        $repository->method('getBlocklistEntries')->willReturn($entries);

        $matcher = new BlocklistMatcher($repository);

        self::assertFalse($matcher->isBlocked('   '));
        self::assertTrue($matcher->isBlocked('user@example.com'));
    }

    public function testNormalizeValueTrimsAndLowercases(): void
    {
        self::assertSame('user@example.com', BlocklistMatcher::normalizeValue('  UsEr@Example.com  '));
    }
}
