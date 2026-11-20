<?php

declare(strict_types=1);

namespace App\Tests\Service\Security;

use App\Service\Security\SecretBox;
use App\Service\Security\UnreadableSecretException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SecretBox::class)]
final class SecretBoxTest extends TestCase
{
    private const string KEY = '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f';
    private const string OTHER_KEY = 'ffeeddccbbaa99887766554433221100ffeeddccbbaa99887766554433221100';

    public function testDecryptsWhatItEncrypted(): void
    {
        $box = new SecretBox(self::KEY);

        $encrypted = $box->encrypt('s3cret pässword', 'settings:password:1');

        self::assertStringStartsWith('v1:', $encrypted);
        self::assertStringNotContainsString('s3cret', $encrypted);
        self::assertSame('s3cret pässword', $box->decrypt($encrypted, 'settings:password:1'));
    }

    public function testTheSameSecretEncryptsDifferentlyEachTime(): void
    {
        $box = new SecretBox(self::KEY);

        self::assertNotSame($box->encrypt('secret', 'context'), $box->encrypt('secret', 'context'));
    }

    public function testASecretDoesNotDecryptInAnotherPlace(): void
    {
        $box = new SecretBox(self::KEY);
        $encrypted = $box->encrypt('secret', 'mailbox:password:1');

        $this->expectException(UnreadableSecretException::class);
        $box->decrypt($encrypted, 'mailbox:password:2');
    }

    public function testASecretDoesNotDecryptWithAnotherKey(): void
    {
        $encrypted = new SecretBox(self::KEY)->encrypt('secret', 'context');

        $this->expectException(UnreadableSecretException::class);
        $this->expectExceptionMessage('another APP_ENCRYPTION_KEY');
        new SecretBox(self::OTHER_KEY)->decrypt($encrypted, 'context');
    }

    public function testATamperedSecretIsRefused(): void
    {
        $box = new SecretBox(self::KEY);
        $encrypted = $box->encrypt('secret', 'context');
        $last = substr($encrypted, -1);
        $tampered = substr($encrypted, 0, -1).('A' === $last ? 'B' : 'A');

        $this->expectException(UnreadableSecretException::class);
        $box->decrypt($tampered, 'context');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedProvider(): iterable
    {
        yield 'plain text' => ['secret'];
        yield 'unknown version' => ['v9:abcd1234:AAAA'];
        yield 'too short' => ['v1:'.self::keyIdOf(self::KEY).':AAAA'];
        yield 'not base64' => ['v1:'.self::keyIdOf(self::KEY).':***'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('malformedProvider')]
    public function testAMalformedSecretIsRefused(string $encrypted): void
    {
        $this->expectException(UnreadableSecretException::class);
        new SecretBox(self::KEY)->decrypt($encrypted, 'context');
    }

    public function testWithoutAKeyItIsNotConfiguredAndRefusesToWork(): void
    {
        $box = new SecretBox(null);

        self::assertFalse($box->isConfigured());
        self::assertFalse(new SecretBox('  ')->isConfigured());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('APP_ENCRYPTION_KEY is not set');
        $box->encrypt('secret', 'context');
    }

    public function testAKeyOfTheWrongShapeIsRefused(): void
    {
        $box = new SecretBox('not-hex');

        self::assertTrue($box->isConfigured());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('64 hexadecimal characters');
        $box->encrypt('secret', 'context');
    }

    private static function keyIdOf(string $hexKey): string
    {
        return substr(hash('sha256', 'dmarco-secret-box-key-id'.sodium_hex2bin($hexKey)), 0, 8);
    }
}
