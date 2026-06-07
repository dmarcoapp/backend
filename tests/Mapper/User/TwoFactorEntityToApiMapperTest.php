<?php

declare(strict_types=1);

namespace App\Tests\Mapper\User;

use App\DTO\Output\User\TwoFactorApi;
use App\Entity\User\User;
use App\Enum\User\TwoFactorMethod;
use App\Mapper\User\TwoFactorEntityToApiMapper;
use App\Service\User\TwoFactor\TwoFactorQrCodeGenerator;
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
#[CoversClass(TwoFactorEntityToApiMapper::class)]
final class TwoFactorEntityToApiMapperTest extends TestCase
{
    public function testPopulateReturnsSetupDataForEmailMode(): void
    {
        $user = (new User())
            ->setEmail('user@example.com')
            ->setTwoFactorMethod(TwoFactorMethod::EMAIL)
        ;

        $mapper = $this->createMapper();

        $dto = $mapper->load($user, TwoFactorApi::class, []);
        $mapper->populate($user, $dto, []);

        self::assertSame('email', $dto->twoFactorMethod);
        self::assertFalse($dto->twoFactorAppEnabled ?? true);
        self::assertNotEmpty($dto->twoFactorAppSecret);
        self::assertStringStartsWith('otpauth://totp/', $dto->twoFactorAppOtpAuthUri ?? '');
        self::assertNotEmpty($dto->twoFactorAppQrContent);
    }

    public function testPopulateHidesSetupDataForAppMode(): void
    {
        $user = (new User())
            ->setEmail('user@example.com')
            ->setTwoFactorMethod(TwoFactorMethod::APP)
            ->setTwoFactorAppSecret('APP_SECRET')
        ;

        $mapper = $this->createMapper();

        $dto = $mapper->load($user, TwoFactorApi::class, []);
        $mapper->populate($user, $dto, []);

        self::assertSame('app', $dto->twoFactorMethod);
        self::assertTrue($dto->twoFactorAppEnabled ?? false);
        self::assertNull($dto->twoFactorAppSecret);
        self::assertNull($dto->twoFactorAppOtpAuthUri);
        self::assertNull($dto->twoFactorAppQrContent);
    }

    private function createMapper(): TwoFactorEntityToApiMapper
    {
        $secretManager = new TwoFactorSecretManager(
            $this->createStub(EntityManagerInterface::class),
            new TwoFactorSecretGenerator(),
        );
        $service = new TwoFactorService($secretManager, new TwoFactorTotpService());

        return new TwoFactorEntityToApiMapper(new TwoFactorQrCodeGenerator(), $service);
    }
}
