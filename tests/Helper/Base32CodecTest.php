<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Helper\Base32Codec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Base32Codec::class)]
final class Base32CodecTest extends TestCase
{
    public function testEncodeAndDecodeRoundTrip(): void
    {
        $input = "hello\x00world";

        $encoded = Base32Codec::encode($input);
        $decoded = Base32Codec::decode($encoded);

        self::assertSame($input, $decoded);
    }

    public function testEncodeAndDecodeEmptyString(): void
    {
        self::assertSame('', Base32Codec::encode(''));
        self::assertSame('', Base32Codec::decode(''));
    }

    public function testDecodeNormalizesCaseAndIgnoresSeparators(): void
    {
        $encoded = Base32Codec::encode("hello\x00world");
        $normalized = strtolower(substr($encoded, 0, 4).'-'.substr($encoded, 4).'=');

        self::assertSame("hello\x00world", Base32Codec::decode($normalized));
    }

    public function testDecodeIgnoresInvalidCharacters(): void
    {
        self::assertSame('', Base32Codec::decode('A@'));
    }
}
