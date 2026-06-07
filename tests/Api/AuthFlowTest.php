<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Controller\Auth\RegistrationController;
use App\Entity\User\User;
use Symfony\Component\String\ByteString;

/**
 * @internal
 *
 * @coversNothing
 */
final class AuthFlowTest extends ApiIntegrationTestCase
{
    public function testRegisterVerifyLoginProfileFlow(): void
    {

        $controller = self::getContainer()->get(RegistrationController::class);
        $registrationEnabledProperty = new \ReflectionProperty($controller, 'registrationEnabled');
        $registrationEnabledProperty->setAccessible(true);
        self::assertTrue((bool) $registrationEnabledProperty->getValue($controller));

        $userRepository = $this->entityManager->getRepository(User::class);
        self::assertNull($userRepository->findOneBy(['email' => 'flow@example.com']));

        $registerResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/register',
            payload: [
                'email' => 'flow@example.com',
                'name' => 'Flow User',
                'password' => 'Str0ngPassw0rd!@#',
            ],
        );

        self::assertSame(200, $registerResponse->getStatusCode(), (string) $registerResponse->getContent());

        $user = $userRepository->findOneBy(['email' => 'flow@example.com']);
        self::assertNotNull($user);
        self::assertNotNull($user->getEmailVerificationToken());

        $verifyResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/verify',
            payload: [
                'email' => 'flow@example.com',
                'token' => $user->getEmailVerificationToken(),
            ],
        );

        self::assertSame(200, $verifyResponse->getStatusCode());

        $loginPayload = $this->loginPayload('flow@example.com', 'Str0ngPassw0rd!@#');
        $token = $loginPayload['token'] ?? null;
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

        $deleteResponse = $this->requestJson(
            method: 'DELETE',
            uri: '/v1/user/profile',
            payload: ['email' => 'flow@example.com'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $deleteResponse->getStatusCode());
        self::assertNull($userRepository->findOneBy(['email' => 'flow@example.com']));
    }

    public function testLoginRejectsUnverifiedUser(): void
    {
        $user = $this->createVerifiedUser('unverified@example.com', 'Str0ngPassw0rd!@#');
        $user->setEmailVerificationToken(ByteString::fromRandom(32)->toString());
        $user->setEmailVerificationTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $this->entityManager->flush();

        $loginResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/login_check',
            payload: [
                'username' => 'unverified@example.com',
                'password' => 'Str0ngPassw0rd!@#',
            ],
        );

        self::assertSame(401, $loginResponse->getStatusCode(), (string) $loginResponse->getContent());
    }

    public function testLogoutRequiresAuthenticationAndSucceedsWithToken(): void
    {
        $this->createVerifiedUser('logout@example.com', 'Str0ngPassw0rd!@#');

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
        $this->createVerifiedUser('logout2@example.com', 'Str0ngPassw0rd!@#');

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
        $this->createVerifiedUser('refresh@example.com', 'Str0ngPassw0rd!@#');

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
        $this->createVerifiedUser('refresh-single@example.com', 'Str0ngPassw0rd!@#');

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
        $this->createVerifiedUser('refresh-logout@example.com', 'Str0ngPassw0rd!@#');

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
