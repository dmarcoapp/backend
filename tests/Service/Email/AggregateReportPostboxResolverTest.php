<?php

declare(strict_types=1);

namespace App\Tests\Service\Email;

use App\Entity\Platform\PlatformSettings;
use App\Service\Email\AggregateReportPostboxResolver;
use App\Tests\Support\PlatformSettingsTestFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AggregateReportPostboxResolver::class)]
final class AggregateReportPostboxResolverTest extends TestCase
{
    public function testRecognizesTheTokenInTheLocalPart(): void
    {
        self::assertTrue($this->resolver()->isReportAddress('sharedtoken123@aggregate-reports.example.test'));
    }

    public function testTheDomainPartDoesNotMatter(): void
    {
        self::assertTrue($this->resolver()->isReportAddress('sharedtoken123@another.example'));
    }

    public function testTrimsSurroundingWhitespace(): void
    {
        self::assertTrue($this->resolver()->isReportAddress("  sharedtoken123@aggregate-reports.example.test\n"));
    }

    public function testAnotherLocalPartIsNotTheReportAddress(): void
    {
        self::assertFalse($this->resolver()->isReportAddress('someone@aggregate-reports.example.test'));
        self::assertFalse($this->resolver()->isReportAddress('sharedtoken123x@aggregate-reports.example.test'));
    }

    public function testStopsAtTheFirstAtSign(): void
    {
        self::assertTrue($this->resolver()->isReportAddress('sharedtoken123@host@aggregate-reports.example.test'));
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
    public function testAnAddressWithoutAnIdentifierIsNotTheReportAddress(string $address): void
    {
        self::assertFalse($this->resolver()->isReportAddress($address));
    }

    private function resolver(): AggregateReportPostboxResolver
    {
        return new AggregateReportPostboxResolver(PlatformSettingsTestFactory::provider(new PlatformSettings('sharedtoken123')));
    }
}
