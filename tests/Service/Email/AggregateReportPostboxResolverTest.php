<?php

declare(strict_types=1);

namespace App\Tests\Service\Email;

use App\Entity\User\User;
use App\Repository\User\UserRepository;
use App\Service\Email\AggregateReportPostboxResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AggregateReportPostboxResolver::class)]
final class AggregateReportPostboxResolverTest extends TestCase
{
    public function testLooksTheOwnerUpByTheLocalPart(): void
    {
        $user = new User();

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['sharedPostboxIdentifierToken' => 'sharedtoken123'])
            ->willReturn($user)
        ;

        $resolver = new AggregateReportPostboxResolver($userRepository);

        self::assertSame($user, $resolver->resolveUser('sharedtoken123@aggregate-reports.example.test'));
    }

    public function testTrimsSurroundingWhitespace(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['sharedPostboxIdentifierToken' => 'sharedtoken123'])
            ->willReturn(null)
        ;

        $resolver = new AggregateReportPostboxResolver($userRepository);

        self::assertNull($resolver->resolveUser("  sharedtoken123@aggregate-reports.example.test\n"));
    }

    public function testStopsAtTheFirstAtSign(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['sharedPostboxIdentifierToken' => 'sharedtoken123'])
            ->willReturn(null)
        ;

        $resolver = new AggregateReportPostboxResolver($userRepository);

        self::assertNull($resolver->resolveUser('sharedtoken123@host@aggregate-reports.example.test'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableAddressProvider(): iterable
    {
        yield 'no domain part' => ['sharedtoken123'];
        yield 'no local part' => ['@aggregate-reports.example.test'];
        yield 'local part is whitespace' => ['   @aggregate-reports.example.test'];
        yield 'empty' => [''];
        yield 'whitespace only' => ['   '];
    }

    #[DataProvider('unusableAddressProvider')]
    public function testAnAddressWithoutAnIdentifierIsNotLookedUp(string $address): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::never())->method('findOneBy');

        $resolver = new AggregateReportPostboxResolver($userRepository);

        self::assertNull($resolver->resolveUser($address));
    }
}
