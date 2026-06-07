<?php

declare(strict_types=1);

namespace App\Tests\Service\User\TwoFactor;

use App\Helper\Base32Codec;
use App\Service\User\TwoFactor\TwoFactorTotpService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TwoFactorTotpService::class)]
final class TwoFactorTotpServiceTest extends TestCase
{
    public function testGenerateAndVerifyCode(): void
    {
        $service = new TwoFactorTotpService();
        $secret = Base32Codec::encode('test-secret');

        $code = $service->generateCode($secret, 30, 6);

        self::assertSame(6, strlen($code));
        self::assertTrue($service->verifyCode($secret, $code, 30, 6, 1));
    }

    public function testVerifyCodeRejectsNonNumericInput(): void
    {
        $service = new TwoFactorTotpService();
        $secret = Base32Codec::encode('test-secret');

        self::assertFalse($service->verifyCode($secret, 'abc123', 30, 6, 1));
    }

    public function testVerifyCodeRejectsWrongNumericCode(): void
    {
        $service = new TwoFactorTotpService();
        $secret = Base32Codec::encode('test-secret');

        self::assertFalse($service->verifyCode($secret, '999999', 30, 6, 0));
    }
}
