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

        $this->createAuthLog($user, AuthLogAction::LOGIN_SUCCESS, '203.0.113.10');
        $this->createAuthLog($user, AuthLogAction::LOGIN_SUCCESS, '198.51.100.7');
        $this->createAuthLog($user, AuthLogAction::LOGIN_FAILURE, '203.0.113.10');

        $repository = $this->entityManager->getRepository(AuthLog::class);
        self::assertInstanceOf(AuthLogRepository::class, $repository);

        self::assertSame(2, $repository->countLoginSuccesses($user->getId()));
    }

    public function testHasLoginSuccessFromIp(): void
    {
        $user = $this->createVerifiedUser('authlog-ip@example.com', 'Str0ngPassw0rd!@#');

        $this->createAuthLog($user, AuthLogAction::LOGIN_SUCCESS, '203.0.113.10');

        $repository = $this->entityManager->getRepository(AuthLog::class);
        self::assertInstanceOf(AuthLogRepository::class, $repository);

        self::assertTrue($repository->hasLoginSuccessFromIp($user->getId(), '203.0.113.10'));
        self::assertFalse($repository->hasLoginSuccessFromIp($user->getId(), '198.51.100.7'));
    }

    public function testDeleteOlderThanRemovesOnlyOldRows(): void
    {
        $user = $this->createVerifiedUser('authlog-delete@example.com', 'Str0ngPassw0rd!@#');

        $oldLog = new AuthLog();
        $oldLog
            ->setUserId($user->getId())
            ->setAction(AuthLogAction::LOGIN_SUCCESS)
            ->setIp('203.0.113.10')
            ->setCreatedAt(new \DateTimeImmutable('-10 days'))
        ;
        $newLog = new AuthLog();
        $newLog
            ->setUserId($user->getId())
            ->setAction(AuthLogAction::LOGIN_SUCCESS)
            ->setIp('203.0.113.10')
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

    private function createAuthLog(User $user, AuthLogAction $action, ?string $ip): void
    {
        $log = new AuthLog();
        $log
            ->setUserId($user->getId())
            ->setAction($action)
            ->setIp($ip)
            ->setCreatedAt(new \DateTimeImmutable())
        ;

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
