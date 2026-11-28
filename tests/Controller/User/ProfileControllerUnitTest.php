<?php

declare(strict_types=1);

namespace App\Tests\Controller\User;

use App\Controller\User\ProfileController;
use App\DTO\Input\User\Profile;
use App\Entity\User\User;
use App\Repository\User\UserRepository;
use App\Service\User\SessionRevoker;
use App\Entity\RefreshToken;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
            $this->sessionRevoker(),
        );

        $user = new User();
        $idProperty = new \ReflectionProperty($user, 'id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, Uuid::v4());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Authenticated user not found.');

        $controller->patch(new Profile(name: 'Updated Name'), $user);
    }

    private function sessionRevoker(?User $expectedUser = null): SessionRevoker
    {
        if (null === $expectedUser) {
            $connection = $this->createStub(Connection::class);
        } else {
            $connection = $this->createMock(Connection::class);
            $connection
                ->expects(self::once())
                ->method('delete')
                ->with('refresh_tokens', ['username' => $expectedUser->getUserIdentifier()])
                ->willReturn(1)
            ;
        }
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getTableName')->willReturn('refresh_tokens');
        $metadata->method('getColumnName')->willReturn('username');
        $entityManager->method('getClassMetadata')->willReturn($metadata);

        return new SessionRevoker(
            $entityManager,
            $this->createStub(JWTTokenManagerInterface::class),
            $this->createStub(RefreshTokenGeneratorInterface::class),
            $this->createStub(RefreshTokenManagerInterface::class),
            2592000,
        );
    }
}
