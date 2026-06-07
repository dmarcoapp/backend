<?php

declare(strict_types=1);

namespace App\Tests\Controller\User;

use App\Controller\User\NotificationController;
use App\DTO\Input\User\Notifications;
use App\Entity\User\User;
use App\Repository\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfonycasts\MicroMapper\MicroMapperInterface;

/**
 * @internal
 */
#[CoversClass(NotificationController::class)]
final class NotificationControllerUnitTest extends TestCase
{
    public function testPatchThrowsLogicExceptionWhenAuthenticatedUserIsMissing(): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())->method('find')->willReturn(null);

        $controller = new NotificationController(
            $this->createStub(EntityManagerInterface::class),
            $repository,
            $this->createStub(MicroMapperInterface::class),
        );

        $user = new User();
        $idProperty = new \ReflectionProperty($user, 'id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, Uuid::v4());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Authenticated user not found.');

        $controller->patch(new Notifications(unusualNewLoginNotificationEnabled: true), $user);
    }
}
