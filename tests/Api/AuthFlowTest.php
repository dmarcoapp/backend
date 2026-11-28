<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User\User;
use App\Enum\User\TwoFactorMethod;
use App\Service\User\TwoFactor\TwoFactorSecretGenerator;
use App\Service\User\UserFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;

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

    public function testPasswordChangeSignsOutEveryOtherSession(): void
    {
        $this->createAccount('password-change@example.com', 'Str0ngPassw0rd!@#');

        $current = $this->loginPayload('password-change@example.com', 'Str0ngPassw0rd!@#');
        $other = $this->loginPayload('password-change@example.com', 'Str0ngPassw0rd!@#');

        $changeResponse = $this->requestJson(
            method: 'PATCH',
            uri: '/v1/user/profile',
            payload: ['password' => 'An0therStr0ng!@#pw', 'currentPassword' => 'Str0ngPassw0rd!@#'],
            headers: $this->authorizeHeaders($current['token']),
        );
        self::assertSame(200, $changeResponse->getStatusCode(), (string) $changeResponse->getContent());
        $changePayload = json_decode((string) $changeResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('password-change@example.com', $changePayload['email'] ?? null);
        self::assertNotEmpty($changePayload['token'] ?? null);
        self::assertNotEmpty($changePayload['refresh_token'] ?? null);

        // Every token from before the change stops working, the caller's too.
        foreach ([$current, $other] as $session) {
            $profileResponse = $this->requestJson(
                method: 'GET',
                uri: '/v1/user/profile',
                headers: $this->authorizeHeaders($session['token']),
            );
            self::assertSame(401, $profileResponse->getStatusCode(), (string) $profileResponse->getContent());

            $refreshResponse = $this->requestJson(
                method: 'POST',
                uri: '/v1/auth/token_refresh',
                payload: ['refresh_token' => $session['refresh_token']],
            );
            self::assertSame(401, $refreshResponse->getStatusCode(), (string) $refreshResponse->getContent());
        }

        // The caller goes on with the session the answer gave it.
        $profileResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/user/profile',
            headers: $this->authorizeHeaders($changePayload['token']),
        );
        self::assertSame(200, $profileResponse->getStatusCode(), (string) $profileResponse->getContent());

        $refreshResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/token_refresh',
            payload: ['refresh_token' => $changePayload['refresh_token']],
        );
        self::assertSame(200, $refreshResponse->getStatusCode(), (string) $refreshResponse->getContent());
    }

    public function testNameChangeKeepsTheSession(): void
    {
        $this->createAccount('name-change@example.com', 'Str0ngPassw0rd!@#');
        $token = $this->login('name-change@example.com', 'Str0ngPassw0rd!@#');

        $patchResponse = $this->requestJson(
            method: 'PATCH',
            uri: '/v1/user/profile',
            payload: ['name' => 'New Name'],
            headers: $this->authorizeHeaders($token),
        );
        self::assertSame(200, $patchResponse->getStatusCode(), (string) $patchResponse->getContent());
        self::assertArrayNotHasKey('token', json_decode((string) $patchResponse->getContent(), true, 512, JSON_THROW_ON_ERROR));

        $profileResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/user/profile',
            headers: $this->authorizeHeaders($token),
        );
        self::assertSame(200, $profileResponse->getStatusCode(), (string) $profileResponse->getContent());
    }

    public function testTokenFromBeforeSessionVersionsKeepsWorkingUntilThePasswordChanges(): void
    {
        $this->createAccount('legacy-token@example.com', 'Str0ngPassw0rd!@#');
        $current = $this->loginPayload('legacy-token@example.com', 'Str0ngPassw0rd!@#');

        // What a backend before the claim issued: no session version in it.
        $legacyToken = self::getContainer()->get(JWTEncoderInterface::class)->encode([
            'username' => 'legacy-token@example.com',
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        $profileResponse = $this->requestJson(method: 'GET', uri: '/v1/user/profile', headers: $this->authorizeHeaders($legacyToken));
        self::assertSame(200, $profileResponse->getStatusCode(), (string) $profileResponse->getContent());

        $changeResponse = $this->requestJson(
            method: 'PATCH',
            uri: '/v1/user/profile',
            payload: ['password' => 'An0therStr0ng!@#pw', 'currentPassword' => 'Str0ngPassw0rd!@#'],
            headers: $this->authorizeHeaders($current['token']),
        );
        self::assertSame(200, $changeResponse->getStatusCode(), (string) $changeResponse->getContent());

        $profileResponse = $this->requestJson(method: 'GET', uri: '/v1/user/profile', headers: $this->authorizeHeaders($legacyToken));
        self::assertSame(401, $profileResponse->getStatusCode(), (string) $profileResponse->getContent());
    }

    public function testSignInThatUpgradesThePasswordHashKeepsItsToken(): void
    {
        $user = $this->createAccount('rehash@example.com', 'Str0ngPassw0rd!@#');
        // A hash at a cost other than the configured one is rewritten at
        // sign-in, after the token has been issued.
        $oldHash = password_hash('Str0ngPassw0rd!@#', PASSWORD_BCRYPT, ['cost' => 5]);
        $user->setPassword($oldHash);
        $this->entityManager->flush();

        $token = $this->login('rehash@example.com', 'Str0ngPassw0rd!@#');

        $this->entityManager->clear();
        $reloaded = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'rehash@example.com']);
        self::assertNotNull($reloaded);
        self::assertNotSame($oldHash, $reloaded->getPassword(), 'The sign-in was expected to upgrade the hash.');

        $profileResponse = $this->requestJson(method: 'GET', uri: '/v1/user/profile', headers: $this->authorizeHeaders($token));
        self::assertSame(200, $profileResponse->getStatusCode(), (string) $profileResponse->getContent());
    }

    public function testRefreshedTokenAfterPasswordChangeWorks(): void
    {
        $this->createAccount('refresh-after-change@example.com', 'Str0ngPassw0rd!@#');
        $current = $this->loginPayload('refresh-after-change@example.com', 'Str0ngPassw0rd!@#');

        $changeResponse = $this->requestJson(
            method: 'PATCH',
            uri: '/v1/user/profile',
            payload: ['password' => 'An0therStr0ng!@#pw', 'currentPassword' => 'Str0ngPassw0rd!@#'],
            headers: $this->authorizeHeaders($current['token']),
        );
        self::assertSame(200, $changeResponse->getStatusCode(), (string) $changeResponse->getContent());
        $changePayload = json_decode((string) $changeResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsString($changePayload['id'] ?? null);

        $refreshResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/token_refresh',
            payload: ['refresh_token' => $changePayload['refresh_token']],
        );
        self::assertSame(200, $refreshResponse->getStatusCode(), (string) $refreshResponse->getContent());
        $refreshed = json_decode((string) $refreshResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $profileResponse = $this->requestJson(method: 'GET', uri: '/v1/user/profile', headers: $this->authorizeHeaders($refreshed['token']));
        self::assertSame(200, $profileResponse->getStatusCode(), (string) $profileResponse->getContent());

        // The old password no longer signs in, the new one does.
        $oldLogin = $this->requestJson(method: 'POST', uri: '/v1/auth/login_check', payload: ['username' => 'refresh-after-change@example.com', 'password' => 'Str0ngPassw0rd!@#']);
        self::assertSame(401, $oldLogin->getStatusCode());
        self::assertNotEmpty($this->login('refresh-after-change@example.com', 'An0therStr0ng!@#pw'));
    }
}
