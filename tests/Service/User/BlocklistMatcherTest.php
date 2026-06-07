<?php

declare(strict_types=1);

namespace App\Tests\Service\User;

use App\Entity\User\BlocklistEntry;
use App\Entity\User\User;
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
        $user = new User();
        $user->setEmail('owner@example.com');
        $user->setName('Owner');
        $user->setSharedPostboxIdentifierToken('shared-token');

        $entries = [
            new BlocklistEntry($user, 'valami@example.com'),
            new BlocklistEntry($user, '*@example.com'),
            new BlocklistEntry($user, '*.example.com'),
            new BlocklistEntry($user, '*@valalmi.*'),
        ];

        $repository = $this->createStub(BlocklistEntryRepository::class);
        $repository->method('getBlocklistEntriesForUser')->willReturn($entries);

        $matcher = new BlocklistMatcher($repository);

        self::assertTrue($matcher->isBlocked($user, 'valami@example.com'));
        self::assertTrue($matcher->isBlocked($user, 'user@example.com'));
        self::assertTrue($matcher->isBlocked($user, 'user@a.example.com'));
        self::assertTrue($matcher->isBlocked($user, 'USER@VALALMI.HU'));
        self::assertFalse($matcher->isBlocked($user, 'user@example.org'));
    }

    public function testIsBlockedReturnsFalseForEmptyAddressAndSkipsEmptyPattern(): void
    {
        $user = new User();
        $user->setEmail('owner@example.com');
        $user->setName('Owner');
        $user->setSharedPostboxIdentifierToken('shared-token');

        $entries = [
            new BlocklistEntry($user, '   '),
            new BlocklistEntry($user, '*@example.com'),
        ];

        $repository = $this->createStub(BlocklistEntryRepository::class);
        $repository->method('getBlocklistEntriesForUser')->willReturn($entries);

        $matcher = new BlocklistMatcher($repository);

        self::assertFalse($matcher->isBlocked($user, '   '));
        self::assertTrue($matcher->isBlocked($user, 'user@example.com'));
    }

    public function testNormalizeValueTrimsAndLowercases(): void
    {
        self::assertSame('user@example.com', BlocklistMatcher::normalizeValue('  UsEr@Example.com  '));
    }
}
