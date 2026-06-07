<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User\User;
use App\Repository\User\UserRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\String\ByteString;

/**
 * @internal
 *
 * @coversNothing
 */
final class ResetPasswordControllerTest extends ApiIntegrationTestCase
{
    public function testResetPasswordReturnsValidationError(): void
    {
        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/reset_password',
            payload: ['email' => 'user@example.com'],
        );

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testResetPasswordReturnsTooManyRequestsWhenApiLimited(): void
    {
        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/reset_password',
            payload: [
                'email' => 'user@example.com',
                'token' => 'token',
                'password' => 'Str0ngPassw0rd!@#',
            ],
            configureContainer: function (ContainerInterface $container): void {
                $userRepository = $this->createStub(UserRepository::class);
                $userRepository->method('findOneBy')->willReturn(null);

                $container->set(UserRepository::class, $userRepository);
                $container->set('limiter.anonymous_api', $this->mockRejectedLimiterFactory());
                $container->set('limiter.anonymous_sendmail', $this->mockAcceptedLimiterFactory());
            },
        );

        self::assertSame(429, $response->getStatusCode());
    }

    public function testResetPasswordReturnsTooManyRequestsWhenSendmailLimited(): void
    {
        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/reset_password',
            payload: [
                'email' => 'user@example.com',
                'token' => 'token',
                'password' => 'Str0ngPassw0rd!@#',
            ],
            configureContainer: function (ContainerInterface $container): void {
                $userRepository = $this->createStub(UserRepository::class);
                $userRepository->method('findOneBy')->willReturn(null);

                $container->set(UserRepository::class, $userRepository);
                $container->set('limiter.anonymous_api', $this->mockAcceptedLimiterFactory());
                $container->set('limiter.anonymous_sendmail', $this->mockRejectedLimiterFactory());
            },
        );

        self::assertSame(429, $response->getStatusCode());
    }

    public function testResetPasswordRejectsExpiredTokenAndClearsIt(): void
    {
        $user = $this->createVerifiedUser('expired@example.com', 'Str0ngPassw0rd!@#');
        $user
            ->setPasswordResetToken(ByteString::fromRandom(32)->toString())
            ->setPasswordResetTokenExpiresAt(new \DateTimeImmutable('-1 hour'))
        ;
        $this->entityManager->flush();

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/reset_password',
            payload: [
                'email' => 'expired@example.com',
                'token' => $user->getPasswordResetToken(),
                'password' => 'An0therStr0ng!Pass',
            ],
        );

        self::assertSame(401, $response->getStatusCode(), (string) $response->getContent());

        $this->entityManager->clear();
        $reloaded = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'expired@example.com']);
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getPasswordResetToken());
        self::assertNull($reloaded->getPasswordResetTokenExpiresAt());
    }
}
