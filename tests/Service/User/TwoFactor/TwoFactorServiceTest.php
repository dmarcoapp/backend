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
    public function testGetMethodDefaultsToEmail(): void
    {
        $service = $this->createService();
        $user = new User();

        self::assertSame(TwoFactorMethod::EMAIL, $service->getMethod($user));
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

    public function testIsValidCodeForEmailAndApp(): void
    {
        $service = $this->createService();
        $totp = new TwoFactorTotpService();

        $emailUser = new User();
        $emailUser->setTwoFactorMethod(TwoFactorMethod::EMAIL);
        $emailUser->setTwoFactorEmailSecret(Base32Codec::encode('email-secret'));
        $emailCode = $totp->generateCode($emailUser->getTwoFactorEmailSecret() ?? '', 300, 6);

        self::assertTrue($service->isValidCode($emailUser, $emailCode));

        $appUser = new User();
        $appUser->setTwoFactorMethod(TwoFactorMethod::APP);
        $appUser->setTwoFactorAppSecret(Base32Codec::encode('app-secret'));
        $appCode = $totp->generateCode($appUser->getTwoFactorAppSecret() ?? '', 30, 6);

        self::assertTrue($service->isValidCode($appUser, $appCode));
    }

    public function testIsValidCodeRejectsMissingAppSecret(): void
    {
        $service = $this->createService();
        $user = new User();
        $user->setTwoFactorMethod(TwoFactorMethod::APP);

        self::assertFalse($service->isValidCode($user, '123456'));
    }

    public function testGetEmailCodeAndAppCodeReturnSixDigits(): void
    {
        $service = $this->createService();
        $codec = new Base32Codec();

        $user = new User();
        $user->setTwoFactorEmailSecret($codec->encode('email-secret'));
        $user->setTwoFactorAppSecret($codec->encode('app-secret'));

        self::assertSame(6, strlen($service->getEmailCode($user)));
        self::assertSame(6, strlen($service->getAppCode($user)));
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
