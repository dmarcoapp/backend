<?php

declare(strict_types=1);

namespace App\Tests\Service\Intake;

use App\Entity\Intake\ApiIntakeKey;
use App\Repository\Intake\ApiIntakeKeyRepository;
use App\Service\Intake\ApiIntakeKeys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ApiIntakeKeys::class)]
final class ApiIntakeKeysTest extends TestCase
{
    public function testAKeyItMadeLetsTheBearerIn(): void
    {
        $stored = null;
        $repository = $this->createStub(ApiIntakeKeyRepository::class);
        $repository->method('save')->willReturnCallback(static function (ApiIntakeKey $key) use (&$stored): void {
            $stored = $key;
        });
        $repository->method('findOneBy')->willReturnCallback(static function (array $criteria) use (&$stored): ?ApiIntakeKey {
            return $stored?->getLookupId() === $criteria['lookupId'] ? $stored : null;
        });

        $keys = new ApiIntakeKeys($repository);
        [$key, $token] = $keys->create('Forwarder');

        self::assertMatchesRegularExpression('/^dmarco_[a-z0-9]{16}_[A-Za-z0-9]{40}$/', $token);
        self::assertStringNotContainsString(substr($token, -40), $key->getSecretHash());
        self::assertSame($key, $keys->authenticate('Bearer '.$token));
        self::assertNotNull($key->getLastUsedAt());
        self::assertSame('dmarco_'.$key->getLookupId().'_…', ApiIntakeKeys::preview($key));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function refusedProvider(): iterable
    {
        yield 'no header' => [null];
        yield 'another scheme' => ['Basic dXNlcjpwYXNz'];
        yield 'a JWT' => ['Bearer eyJhbGciOiJIUzI1NiJ9.e30.ZRrHA1JJJW8opsbCGfG_HACGpVUMN_a9IV7pAx_Zmeo'];
        yield 'the right lookup id with another secret' => ['Bearer dmarco_abcdefghijklmnop_'.str_repeat('B', 40)];
        yield 'a key with something after it' => ['Bearer dmarco_abcdefghijklmnop_'.str_repeat('A', 40).' x'];
    }

    #[DataProvider('refusedProvider')]
    public function testRefusesAnythingElse(?string $authorization): void
    {
        $key = new ApiIntakeKey('Forwarder', 'abcdefghijklmnop', hash('sha256', str_repeat('A', 40)));
        $repository = $this->createStub(ApiIntakeKeyRepository::class);
        $repository->method('findOneBy')->willReturn($key);

        self::assertNull(new ApiIntakeKeys($repository)->authenticate($authorization));
    }
}
