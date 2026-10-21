<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User\User;
use App\Enum\User\TwoFactorMethod;
use App\Service\User\TwoFactor\TwoFactorSecretGenerator;
use App\Service\User\UserFactory;

/**
 * @internal
 *
 * @coversNothing
 */
final class AuthFlowTest extends ApiIntegrationTestCase
{
    public function testAccountFromTheCommandLineSignsInWithItsPassword(): void
    {
        self::getContainer()->get(UserFactory::class)->create(
            email: 'flow@example.com',
            name: 'Flow User',
            plainPassword: 'Str0ngPassw0rd!@#',
        );

        // Two-factor authentication starts off, so the password alone signs in.
        $loginResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/login_check',
            payload: [
                'username' => 'flow@example.com',
                'password' => 'Str0ngPassw0rd!@#',
            ],
        );
        self::assertSame(200, $loginResponse->getStatusCode(), (string) $loginResponse->getContent());
        $token = json_decode((string) $loginResponse->getContent(), true, 512, JSON_THROW_ON_ERROR)['token'] ?? null;
        self::assertNotEmpty($token);

        $profileResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/user/profile',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $profileResponse->getStatusCode());
        $profilePayload = json_decode((string) $profileResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('flow@example.com', $profilePayload['email'] ?? null);

        $patchResponse = $this->requestJson(
            method: 'PATCH',
            uri: '/v1/user/profile',
            payload: ['name' => 'Flow User Updated'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $patchResponse->getStatusCode());

        // The account can only be removed on the server.
        $deleteResponse = $this->requestJson(
            method: 'DELETE',
            uri: '/v1/user/profile',
            payload: ['email' => 'flow@example.com'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(405, $deleteResponse->getStatusCode());
        self::assertNotNull($this->entityManager->getRepository(User::class)->findOneBy(['email' => 'flow@example.com']));
    }

    public function testLoginAsksForACodeOnceTwoFactorIsOn(): void
    {
        $user = $this->createAccount('two-factor@example.com', 'Str0ngPassw0rd!@#');
        $user
            ->setTwoFactorMethod(TwoFactorMethod::APP)
            ->setTwoFactorAppSecret(self::getContainer()->get(TwoFactorSecretGenerator::class)->generate())
        ;
        $this->entityManager->flush();

        $loginResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/login_check',
            payload: [
                'username' => 'two-factor@example.com',
                'password' => 'Str0ngPassw0rd!@#',
            ],
        );

        self::assertSame(401, $loginResponse->getStatusCode(), (string) $loginResponse->getContent());
        self::assertNotEmpty($this->login('two-factor@example.com', 'Str0ngPassw0rd!@#'));
    }

    public function testLogoutRequiresAuthenticationAndSucceedsWithToken(): void
    {
        $this->createAccount('logout@example.com', 'Str0ngPassw0rd!@#');

        $unauthResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/user/logout',
            payload: ['refresh_token' => 'dummy'],
        );

        self::assertSame(401, $unauthResponse->getStatusCode());

        $token = $this->login('logout@example.com', 'Str0ngPassw0rd!@#');

        $authResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/user/logout',
            payload: ['refresh_token' => 'dummy'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $authResponse->getStatusCode(), (string) $authResponse->getContent());
    }

    public function testLogoutInvalidatesToken(): void
    {
        $this->createAccount('logout2@example.com', 'Str0ngPassw0rd!@#');

        $token = $this->login('logout2@example.com', 'Str0ngPassw0rd!@#');

        $logoutResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/user/logout',
            payload: ['refresh_token' => 'dummy'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $logoutResponse->getStatusCode(), (string) $logoutResponse->getContent());

        $profileResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/user/profile',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(401, $profileResponse->getStatusCode(), (string) $profileResponse->getContent());
    }

    public function testRefreshTokenFlowReturnsNewAccessToken(): void
    {
        $this->createAccount('refresh@example.com', 'Str0ngPassw0rd!@#');

        $loginPayload = $this->loginPayload('refresh@example.com', 'Str0ngPassw0rd!@#');
        $refreshToken = $loginPayload['refresh_token'] ?? null;
        self::assertNotEmpty($refreshToken);

        $refreshResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/token_refresh',
            payload: ['refresh_token' => $refreshToken],
        );

        self::assertSame(200, $refreshResponse->getStatusCode(), (string) $refreshResponse->getContent());
        $refreshPayload = json_decode((string) $refreshResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNotEmpty($refreshPayload['token'] ?? null);
        self::assertNotEmpty($refreshPayload['refresh_token'] ?? null);
    }

    public function testRefreshTokenIsSingleUse(): void
    {
        $this->createAccount('refresh-single@example.com', 'Str0ngPassw0rd!@#');

        $loginPayload = $this->loginPayload('refresh-single@example.com', 'Str0ngPassw0rd!@#');
        $refreshToken = $loginPayload['refresh_token'] ?? null;
        self::assertNotEmpty($refreshToken);

        $firstRefreshResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/token_refresh',
            payload: ['refresh_token' => $refreshToken],
        );

        self::assertSame(200, $firstRefreshResponse->getStatusCode(), (string) $firstRefreshResponse->getContent());

        $secondRefreshResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/token_refresh',
            payload: ['refresh_token' => $refreshToken],
        );

        self::assertNotSame(200, $secondRefreshResponse->getStatusCode(), (string) $secondRefreshResponse->getContent());
    }

    public function testLogoutInvalidatesRefreshToken(): void
    {
        $this->createAccount('refresh-logout@example.com', 'Str0ngPassw0rd!@#');

        $loginPayload = $this->loginPayload('refresh-logout@example.com', 'Str0ngPassw0rd!@#');
        $accessToken = $loginPayload['token'] ?? null;
        $refreshToken = $loginPayload['refresh_token'] ?? null;
        self::assertNotEmpty($accessToken);
        self::assertNotEmpty($refreshToken);

        $logoutResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/user/logout',
            payload: ['refresh_token' => $refreshToken],
            headers: $this->authorizeHeaders($accessToken),
        );

        self::assertSame(200, $logoutResponse->getStatusCode(), (string) $logoutResponse->getContent());

        $refreshAfterLogoutResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/token_refresh',
            payload: ['refresh_token' => $refreshToken],
        );

        self::assertNotSame(200, $refreshAfterLogoutResponse->getStatusCode(), (string) $refreshAfterLogoutResponse->getContent());
    }
}
