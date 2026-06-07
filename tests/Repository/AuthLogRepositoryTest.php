<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\User\AuthLog;
use App\Entity\User\User;
use App\Enum\Auth\AuthLogAction;
use App\Repository\User\AuthLogRepository;
use App\Tests\Api\ApiIntegrationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class AuthLogRepositoryTest extends ApiIntegrationTestCase
{
    public function testCountLoginSuccesses(): void
    {
        $user = $this->createVerifiedUser('authlog@example.com', 'Str0ngPassw0rd!@#');

        $this->createAuthLog($user, AuthLogAction::LOGIN_SUCCESS, 'HU');
        $this->createAuthLog($user, AuthLogAction::LOGIN_SUCCESS, 'DE');
        $this->createAuthLog($user, AuthLogAction::LOGIN_FAILURE, 'HU');

        $repository = $this->entityManager->getRepository(AuthLog::class);
        self::assertInstanceOf(AuthLogRepository::class, $repository);

        self::assertSame(2, $repository->countLoginSuccesses($user->getId()));
    }

    public function testHasLoginSuccessFromCountry(): void
    {
        $user = $this->createVerifiedUser('authlog-country@example.com', 'Str0ngPassw0rd!@#');

        $this->createAuthLog($user, AuthLogAction::LOGIN_SUCCESS, 'HU');

        $repository = $this->entityManager->getRepository(AuthLog::class);
        self::assertInstanceOf(AuthLogRepository::class, $repository);

        self::assertTrue($repository->hasLoginSuccessFromCountry($user->getId(), 'HU'));
        self::assertFalse($repository->hasLoginSuccessFromCountry($user->getId(), 'DE'));
    }

    public function testDeleteOlderThanRemovesOnlyOldRows(): void
    {
        $user = $this->createVerifiedUser('authlog-delete@example.com', 'Str0ngPassw0rd!@#');

        $oldLog = new AuthLog();
        $oldLog
            ->setUserId($user->getId())
            ->setAction(AuthLogAction::LOGIN_SUCCESS)
            ->setCountryCode('HU')
            ->setCreatedAt(new \DateTimeImmutable('-10 days'))
        ;
        $newLog = new AuthLog();
        $newLog
            ->setUserId($user->getId())
            ->setAction(AuthLogAction::LOGIN_SUCCESS)
            ->setCountryCode('HU')
            ->setCreatedAt(new \DateTimeImmutable('-1 day'))
        ;

        $this->entityManager->persist($oldLog);
        $this->entityManager->persist($newLog);
        $this->entityManager->flush();

        $repository = $this->entityManager->getRepository(AuthLog::class);
        self::assertInstanceOf(AuthLogRepository::class, $repository);

        self::assertSame(2, $repository->count(['userId' => $user->getId()]));
        $deleted = $repository->deleteOlderThan(new \DateTimeImmutable('-5 days'));

        self::assertSame(1, $deleted);
        self::assertSame(1, $repository->count(['userId' => $user->getId()]));
    }

    private function createAuthLog(User $user, AuthLogAction $action, ?string $countryCode): void
    {
        $log = new AuthLog();
        $log
            ->setUserId($user->getId())
            ->setAction($action)
            ->setCountryCode($countryCode)
            ->setCreatedAt(new \DateTimeImmutable())
        ;

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
