<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\User\UserRepository;
use App\Tests\Api\ApiIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Uid\Uuid;

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
        $user = $this->createVerifiedUser('upgrade-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $repository = self::getContainer()->get(UserRepository::class);
        $repository->upgradePassword($user, 'new-hash');

        $this->entityManager->refresh($user);
        self::assertSame('new-hash', $user->getPassword());
    }

    public function testGetUnverifiedUserIdsToDelete(): void
    {
        $staleUser = $this->createVerifiedUser('stale-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');
        $staleUser
            ->setEmailVerificationToken('token')
            ->setEmailVerificationTokenExpiresAt(new \DateTimeImmutable('-2 hours'))
        ;

        $recentUser = $this->createVerifiedUser('recent-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');
        $recentUser
            ->setEmailVerificationToken('token-recent')
            ->setEmailVerificationTokenExpiresAt(new \DateTimeImmutable('+2 hours'))
        ;

        $this->entityManager->flush();

        $repository = self::getContainer()->get(UserRepository::class);
        $ids = $repository->getUnverifiedUserIdsToDelete();
        $idValues = array_map(static fn (mixed $id): string => self::normalizeUuid($id), $ids);

        self::assertContains($staleUser->getId()?->toRfc4122(), $idValues);
        self::assertNotContains($recentUser->getId()?->toRfc4122(), $idValues);
    }

    public function testGetAllUserIdsHonorsLimit(): void
    {
        $this->createVerifiedUser('limit-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');
        $this->createVerifiedUser('limit2-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $repository = self::getContainer()->get(UserRepository::class);
        $ids = $repository->getAllUserIds(1);

        self::assertCount(1, $ids);
    }

    public function testDeleteByIdsRemovesUser(): void
    {
        $repository = self::getContainer()->get(UserRepository::class);
        $user = $this->createVerifiedUser('delete-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $ids = $repository->getAllUserIds();
        $userId = null;
        foreach ($ids as $rawId) {
            if (self::normalizeUuid($rawId) === $user->getId()?->toRfc4122()) {
                $userId = $rawId;

                break;
            }
        }
        self::assertNotNull($userId);

        $repository->deleteByIds([$userId]);

        $this->entityManager->clear();
        self::assertNull($repository->find($userId));
    }

    public function testSaveAndDelete(): void
    {
        $user = $this->createVerifiedUser('save-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');
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

    private static function normalizeUuid(mixed $value): string
    {
        if ($value instanceof Uuid) {
            return $value->toRfc4122();
        }

        if (is_string($value) && 16 === strlen($value)) {
            return Uuid::fromBinary($value)->toRfc4122();
        }

        return Uuid::fromString((string) $value)->toRfc4122();
    }
}
