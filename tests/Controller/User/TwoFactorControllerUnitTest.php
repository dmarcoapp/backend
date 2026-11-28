<?php

declare(strict_types=1);

namespace App\Tests\Controller\User;

use App\Controller\User\TwoFactorController;
use App\DTO\Input\User\TwoFactorAppDisable;
use App\Entity\User\User;
use App\Repository\User\UserRepository;
use App\Service\User\TwoFactor\TwoFactorSecretGenerator;
use App\Service\User\TwoFactor\TwoFactorSecretManager;
use App\Service\User\TwoFactor\TwoFactorService;
use App\Service\User\TwoFactor\TwoFactorStatusMailer;
use App\Service\User\TwoFactor\TwoFactorTotpService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Uid\Uuid;
use Symfonycasts\MicroMapper\MicroMapperInterface;

/**
 * @internal
 */
#[CoversClass(TwoFactorController::class)]
final class TwoFactorControllerUnitTest extends TestCase
{
    public function testDisableThrowsLogicExceptionWhenAuthenticatedUserIsMissing(): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())->method('find')->willReturn(null);

        $controller = new TwoFactorController(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(MicroMapperInterface::class),
            new TwoFactorService(
                new TwoFactorSecretManager(
                    $this->createStub(EntityManagerInterface::class),
                    new TwoFactorSecretGenerator(),
                ),
                new TwoFactorTotpService(),
            ),
            new TwoFactorStatusMailer($this->createStub(MailerInterface::class)),
            $repository,
            $this->createStub(RateLimiterFactoryInterface::class),
            $this->createStub(EventDispatcherInterface::class),
        );

        $user = new User();
        $idProperty = new \ReflectionProperty($user, 'id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, Uuid::v4());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Authenticated user not found.');

        $controller->disableTwoFactorApp(new TwoFactorAppDisable('123456'), $user);
    }
}
