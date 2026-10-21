<?php

declare(strict_types=1);

namespace App\Tests\Service\User\TwoFactor;

use App\Entity\User\User;
use App\Enum\User\TwoFactorMethod;
use App\Helper\Base32Codec;
use App\Service\User\TwoFactor\TwoFactorSecretGenerator;
use App\Service\User\TwoFactor\TwoFactorSecretManager;
use App\Service\User\TwoFactor\TwoFactorService;
use App\Service\User\TwoFactor\TwoFactorTotpService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TwoFactorService::class)]
final class TwoFactorServiceTest extends TestCase
{
    public function testTwoFactorIsOffByDefault(): void
    {
        $service = $this->createService();
        $user = new User();

        self::assertNull($service->getMethod($user));
        self::assertFalse($service->isEnabled($user));
    }

    public function testGetAppOtpAuthUriContainsSecretAndIssuer(): void
    {
        $service = $this->createService();
        $user = new User();
        $user->setEmail('user@example.com');

        $secret = 'BASE32SECRET';
        $uri = $service->getAppOtpAuthUri($user, $secret);

        self::assertStringContainsString('otpauth://totp/', $uri);
        self::assertStringContainsString('secret='.$secret, $uri);
        self::assertStringContainsString('issuer=DMARCo', $uri);
    }

    public function testIsValidCodeForApp(): void
    {
        $service = $this->createService();
        $totp = new TwoFactorTotpService();

        $appUser = new User();
        $appUser->setTwoFactorMethod(TwoFactorMethod::APP);
        $appUser->setTwoFactorAppSecret(Base32Codec::encode('app-secret'));
        $appCode = $totp->generateCode($appUser->getTwoFactorAppSecret() ?? '', 30, 6);

        self::assertTrue($service->isValidCode($appUser, $appCode));
        self::assertTrue($service->isEnabled($appUser));
    }

    public function testIsValidCodeRejectsEveryCodeWhileOff(): void
    {
        $service = $this->createService();

        // A secret left behind from an earlier setup does not count.
        $user = new User();
        $user->setTwoFactorAppSecret(Base32Codec::encode('app-secret'));

        self::assertFalse($service->isValidCode($user, $service->getAppCode($user)));
    }

    public function testIsValidCodeRejectsMissingAppSecret(): void
    {
        $service = $this->createService();
        $user = new User();
        $user->setTwoFactorMethod(TwoFactorMethod::APP);

        self::assertFalse($service->isValidCode($user, '123456'));
    }

    public function testGetAppCodeReturnsSixDigits(): void
    {
        $service = $this->createService();

        $user = new User();
        $user->setTwoFactorAppSecret(Base32Codec::encode('app-secret'));

        self::assertSame(6, strlen($service->getAppCode($user)));
    }

    public function testAppCodeStaysReusableWithinItsWindow(): void
    {
        $service = $this->createService();

        $user = new User();
        $user->setTwoFactorMethod(TwoFactorMethod::APP);
        $user->setTwoFactorAppSecret(Base32Codec::encode('app-secret'));

        $code = $service->getAppCode($user);

        // An authenticator derives its codes from a secret the owner shares with
        // us, which cannot be rotated here, so a code is not spent on use.
        self::assertTrue($service->isValidCode($user, $code));
        self::assertTrue($service->isValidCode($user, $code));
    }

    private function createService(): TwoFactorService
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $generator = new TwoFactorSecretGenerator();
        $secretManager = new TwoFactorSecretManager($entityManager, $generator);
        $totpService = new TwoFactorTotpService();

        return new TwoFactorService($secretManager, $totpService);
    }
}
