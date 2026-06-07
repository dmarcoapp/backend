<?php

declare(strict_types=1);

namespace App\Tests\Mapper\User;

use App\DTO\Output\User\UserApi;
use App\Entity\User\User;
use App\Mapper\User\UserEntityToApiMapper;
use App\Service\Email\AggregateReportPostboxAddressProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(UserEntityToApiMapper::class)]
final class UserEntityToApiMapperTest extends TestCase
{
    public function testLoadAndPopulateMapsUserFields(): void
    {
        $user = new User();
        $userId = Uuid::v4();
        $this->setEntityId($user, $userId);

        $user
            ->setEmail('user@example.com')
            ->setName('Example User')
            ->setSharedPostboxIdentifierToken('shared-token')
        ;

        $provider = new AggregateReportPostboxAddressProvider('reports.example.com');
        $mapper = new UserEntityToApiMapper($provider);

        $dto = $mapper->load($user, UserApi::class, []);
        $mapper->populate($user, $dto, []);

        self::assertSame($userId, $dto->id);
        self::assertSame('user@example.com', $dto->email);
        self::assertSame('Example User', $dto->name);
        self::assertSame('shared-token@reports.example.com', $dto->sharedAggregatePostboxAddress);
    }

    public function testLoadThrowsWhenUserIdIsMissing(): void
    {
        $user = new User();
        $user
            ->setEmail('user@example.com')
            ->setName('Example User')
            ->setSharedPostboxIdentifierToken('shared-token')
        ;

        $provider = new AggregateReportPostboxAddressProvider('reports.example.com');
        $mapper = new UserEntityToApiMapper($provider);

        $this->expectException(\LogicException::class);
        $mapper->load($user, UserApi::class, []);
    }

    private function setEntityId(object $entity, Uuid $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
