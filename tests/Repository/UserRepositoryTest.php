<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\User\UserRepository;
use App\Tests\Api\ApiIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * @internal
 */
#[CoversClass(UserRepository::class)]
final class UserRepositoryTest extends ApiIntegrationTestCase
{
    public function testUpgradePasswordThrowsForUnsupportedUser(): void
    {
        $repository = self::getContainer()->get(UserRepository::class);

        $this->expectException(UnsupportedUserException::class);

        $repository->upgradePassword(new InMemoryUser('user', 'pass'), 'hash');
    }

    public function testUpgradePasswordUpdatesUser(): void
    {
        $user = $this->createAccount('upgrade-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $repository = self::getContainer()->get(UserRepository::class);
        $repository->upgradePassword($user, 'new-hash');

        $this->entityManager->refresh($user);
        self::assertSame('new-hash', $user->getPassword());
    }

    public function testHasAnyOnceAnAccountExists(): void
    {
        $this->createAccount('any-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        self::assertTrue(self::getContainer()->get(UserRepository::class)->hasAny());
    }

    public function testGetAllUserIdsHonorsLimit(): void
    {
        $this->createAccount('limit-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');
        $this->createAccount('limit2-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $repository = self::getContainer()->get(UserRepository::class);
        $ids = $repository->getAllUserIds(1);

        self::assertCount(1, $ids);
    }

    public function testSaveAndDelete(): void
    {
        $user = $this->createAccount('save-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');
        $user->setName('Updated');
        $userId = $user->getId();
        self::assertNotNull($userId);

        $repository = self::getContainer()->get(UserRepository::class);
        $repository->save($user);

        $this->entityManager->refresh($user);
        self::assertSame('Updated', $user->getName());

        $repository->delete($user);
        $this->entityManager->clear();
        self::assertNull($repository->find($userId));
    }
}
