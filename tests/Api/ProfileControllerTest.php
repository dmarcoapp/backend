<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @internal
 *
 * @coversNothing
 */
final class ProfileControllerTest extends ApiIntegrationTestCase
{
    public function testPatchUpdatesNameAndPassword(): void
    {
        $this->createVerifiedUser('profile@example.com', 'Str0ngPassw0rd!@#', 'Profile User');

        $token = $this->login('profile@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'PATCH',
            uri: '/v1/user/profile',
            payload: [
                'name' => 'Profile User Updated',
                'password' => 'N3wStr0ngPass!@#',
            ],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $this->entityManager->clear();
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'profile@example.com']);
        self::assertNotNull($user);
        self::assertSame('Profile User Updated', $user->getName());

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, 'N3wStr0ngPass!@#'));
    }

    public function testDeleteRejectsMismatchedEmail(): void
    {
        $this->createVerifiedUser('delete@example.com', 'Str0ngPassw0rd!@#');

        $token = $this->login('delete@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'DELETE',
            uri: '/v1/user/profile',
            payload: ['email' => 'other@example.com'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testDeleteRemovesProfile(): void
    {
        $this->createVerifiedUser('delete-ok@example.com', 'Str0ngPassw0rd!@#');

        $token = $this->login('delete-ok@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'DELETE',
            uri: '/v1/user/profile',
            payload: ['email' => 'delete-ok@example.com'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $this->entityManager->clear();
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'delete-ok@example.com']);
        self::assertNull($user);
    }

    public function testGetAndPatchNotifications(): void
    {
        $this->createVerifiedUser('notifications@example.com', 'Str0ngPassw0rd!@#');

        $token = $this->login('notifications@example.com', 'Str0ngPassw0rd!@#');

        $getResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/user/notifications',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $getResponse->getStatusCode(), (string) $getResponse->getContent());
        $getPayload = json_decode((string) $getResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($getPayload['unusualNewLoginNotificationEnabled'] ?? false);
        self::assertTrue($getPayload['weeklyOverviewNotificationEnabled'] ?? false);

        $patchResponse = $this->requestJson(
            method: 'PATCH',
            uri: '/v1/user/notifications',
            payload: [
                'unusualNewLoginNotificationEnabled' => false,
                'weeklyOverviewNotificationEnabled' => false,
            ],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $patchResponse->getStatusCode(), (string) $patchResponse->getContent());
        $patchPayload = json_decode((string) $patchResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($patchPayload['unusualNewLoginNotificationEnabled'] ?? true);
        self::assertFalse($patchPayload['weeklyOverviewNotificationEnabled'] ?? true);

        $this->entityManager->clear();
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'notifications@example.com']);
        self::assertNotNull($user);
        self::assertFalse($user->isUnusualNewLoginNotificationEnabled());
        self::assertFalse($user->isWeeklyOverviewNotificationEnabled());
    }

    public function testGetReturnsTooManyRequestsWhenLoggedInRateLimited(): void
    {
        $this->createVerifiedUser('rate-limit-profile@example.com', 'Str0ngPassw0rd!@#');

        $token = $this->login('rate-limit-profile@example.com', 'Str0ngPassw0rd!@#');
        self::ensureKernelShutdown();

        $response = $this->requestJson(
            method: 'GET',
            uri: '/v1/user/profile',
            headers: $this->authorizeHeaders($token),
            configureContainer: function (ContainerInterface $container): void {
                $container->set('limiter.logged_in_interaction', $this->mockRejectedLimiterFactory());
            },
        );

        self::assertSame(429, $response->getStatusCode(), (string) $response->getContent());
    }
}
