<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Repository\User\UserRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @coversNothing
 */
final class AuthEndpointsTest extends ApiTestCase
{
    public function testStatusReportsAFreshInstall(): void
    {
        $response = $this->requestStatus(hasAccount: false);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(
            ['accountExists' => false],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testStatusReportsAnExistingAccount(): void
    {
        $response = $this->requestStatus(hasAccount: true);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(
            ['accountExists' => true],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testStatusReturnsTooManyRequestsWhenApiLimited(): void
    {
        $response = $this->requestStatus(hasAccount: true, limited: true);

        self::assertSame(429, $response->getStatusCode());
    }

    private function requestStatus(bool $hasAccount, bool $limited = false): Response
    {
        return $this->requestJson(
            method: 'GET',
            uri: '/v1/auth/status',
            configureContainer: function (ContainerInterface $container) use ($hasAccount, $limited): void {
                $userRepository = $this->createStub(UserRepository::class);
                $userRepository->method('hasAny')->willReturn($hasAccount);

                $container->set(UserRepository::class, $userRepository);
                $container->set(
                    'limiter.anonymous_api',
                    $limited ? $this->mockRejectedLimiterFactory() : $this->mockAcceptedLimiterFactory(),
                );
            },
        );
    }
}
