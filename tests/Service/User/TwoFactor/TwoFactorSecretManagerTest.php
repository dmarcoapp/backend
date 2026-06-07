<?php

declare(strict_types=1);

namespace App\Tests\Service\User\TwoFactor;

use App\Entity\User\User;
use App\Service\User\TwoFactor\TwoFactorSecretGenerator;
use App\Service\User\TwoFactor\TwoFactorSecretManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TwoFactorSecretManager::class)]
final class TwoFactorSecretManagerTest extends TestCase
{
    public function testEnsureEmailSecretReturnsExistingWithoutFlush(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $generator = new TwoFactorSecretGenerator();
        $manager = new TwoFactorSecretManager($entityManager, $generator);

        $user = new User();
        $user->setTwoFactorEmailSecret('EXISTINGSECRET');

        $secret = $manager->ensureEmailSecret($user);

        self::assertSame('EXISTINGSECRET', $secret);
    }

    public function testEnsureEmailSecretGeneratesAndFlushes(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $generator = new TwoFactorSecretGenerator();
        $manager = new TwoFactorSecretManager($entityManager, $generator);

        $user = new User();

        $secret = $manager->ensureEmailSecret($user);

        self::assertNotSame('', $secret);
        self::assertSame($secret, $user->getTwoFactorEmailSecret());
    }

    public function testEnsureAppSecretGeneratesAndFlushes(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $generator = new TwoFactorSecretGenerator();
        $manager = new TwoFactorSecretManager($entityManager, $generator);

        $user = new User();

        $secret = $manager->ensureAppSecret($user);

        self::assertNotSame('', $secret);
        self::assertSame($secret, $user->getTwoFactorAppSecret());
    }
}
