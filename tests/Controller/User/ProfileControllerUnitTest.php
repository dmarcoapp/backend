<?php

declare(strict_types=1);

namespace App\Tests\Controller\User;

use App\Controller\User\ProfileController;
use App\DTO\Input\User\Profile;
use App\Entity\User\User;
use App\Repository\User\UserRepository;
use App\Service\User\UserDeleteProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Symfonycasts\MicroMapper\MicroMapperInterface;

/**
 * @internal
 */
#[CoversClass(ProfileController::class)]
final class ProfileControllerUnitTest extends TestCase
{
    public function testPatchThrowsLogicExceptionWhenAuthenticatedUserIsMissing(): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())->method('find')->willReturn(null);

        $controller = new ProfileController(
            $this->createStub(EntityManagerInterface::class),
            $repository,
            $this->createStub(UserPasswordHasherInterface::class),
            $this->createStub(EventDispatcherInterface::class),
            $this->createStub(MicroMapperInterface::class),
            new UserDeleteProcessor(
                $this->createStub(UserRepository::class),
                $this->createStub(LoggerInterface::class),
            ),
        );

        $user = new User();
        $idProperty = new \ReflectionProperty($user, 'id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, Uuid::v4());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Authenticated user not found.');

        $controller->patch(new Profile(name: 'Updated Name'), $user);
    }
}
