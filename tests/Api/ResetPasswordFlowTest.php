<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User\User;

/**
 * @internal
 *
 * @coversNothing
 */
final class ResetPasswordFlowTest extends ApiIntegrationTestCase
{
    public function testPasswordResetFlow(): void
    {
        $this->createVerifiedUser('reset@example.com', 'Str0ngPassw0rd!@#');

        $requestResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/request_password_reset',
            payload: ['email' => 'reset@example.com'],
        );

        self::assertSame(200, $requestResponse->getStatusCode());

        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => 'reset@example.com']);
        self::assertNotNull($user);
        self::assertNotNull($user->getPasswordResetToken());

        $resetResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/reset_password',
            payload: [
                'email' => 'reset@example.com',
                'token' => $user->getPasswordResetToken(),
                'password' => 'An0therStr0ng!Pass',
            ],
        );

        self::assertSame(200, $resetResponse->getStatusCode());

        $token = $this->login('reset@example.com', 'An0therStr0ng!Pass');
        self::assertNotEmpty($token);
    }
}
