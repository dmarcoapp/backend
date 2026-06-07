<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Repository\User\UserRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 *
 * @coversNothing
 */
final class AuthEndpointsTest extends ApiTestCase
{
    public function testRequestPasswordResetReturnsSuccess(): void
    {
        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/request_password_reset',
            payload: ['email' => 'user@example.com'],
            configureContainer: function (ContainerInterface $container): void {
                $userRepository = $this->createStub(UserRepository::class);
                $userRepository->method('findOneBy')->willReturn(null);

                $container->set(UserRepository::class, $userRepository);
                $container->set('limiter.anonymous_api', $this->mockAcceptedLimiterFactory());
                $container->set('limiter.anonymous_sendmail', $this->mockAcceptedLimiterFactory());
            },
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Password reset request sent.', (string) $response->getContent());
    }

    public function testRequestPasswordResetReturnsValidationError(): void
    {
        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/request_password_reset',
            payload: [],
        );

        self::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testRequestPasswordResetReturnsTooManyRequestsWhenApiLimited(): void
    {
        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/request_password_reset',
            payload: ['email' => 'user@example.com'],
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

    public function testRequestPasswordResetReturnsTooManyRequestsWhenSendmailLimited(): void
    {
        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/request_password_reset',
            payload: ['email' => 'user@example.com'],
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

    public function testRequestVerificationResendReturnsSuccess(): void
    {
        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/request_verification_resend',
            payload: ['email' => 'user@example.com'],
            configureContainer: function (ContainerInterface $container): void {
                $userRepository = $this->createStub(UserRepository::class);
                $userRepository->method('findOneBy')->willReturn(null);

                $container->set(UserRepository::class, $userRepository);
                $container->set('limiter.anonymous_api', $this->mockAcceptedLimiterFactory());
                $container->set('limiter.anonymous_sendmail', $this->mockAcceptedLimiterFactory());
            },
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Email verification email resent.', (string) $response->getContent());
    }

    public function testRequestVerificationResendReturnsValidationError(): void
    {
        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/request_verification_resend',
            payload: [],
        );

        self::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testRequestVerificationResendReturnsTooManyRequestsWhenApiLimited(): void
    {
        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/request_verification_resend',
            payload: ['email' => 'user@example.com'],
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

    public function testRequestVerificationResendReturnsTooManyRequestsWhenSendmailLimited(): void
    {
        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/request_verification_resend',
            payload: ['email' => 'user@example.com'],
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

    public function testVerifyReturnsNotFoundForInvalidToken(): void
    {
        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/verify',
            payload: ['email' => 'user@example.com', 'token' => 'invalid'],
            configureContainer: function (ContainerInterface $container): void {
                $userRepository = $this->createStub(UserRepository::class);
                $userRepository->method('findOneBy')->willReturn(null);

                $container->set(UserRepository::class, $userRepository);
                $container->set('limiter.anonymous_api', $this->mockAcceptedLimiterFactory());
            },
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testVerifyReturnsValidationError(): void
    {
        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/verify',
            payload: ['email' => 'user@example.com'],
        );

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testVerifyReturnsTooManyRequestsWhenApiLimited(): void
    {
        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/verify',
            payload: ['email' => 'user@example.com', 'token' => 'invalid'],
            configureContainer: function (ContainerInterface $container): void {
                $userRepository = $this->createStub(UserRepository::class);
                $userRepository->method('findOneBy')->willReturn(null);

                $container->set(UserRepository::class, $userRepository);
                $container->set('limiter.anonymous_api', $this->mockRejectedLimiterFactory());
            },
        );

        self::assertSame(429, $response->getStatusCode());
    }
}
