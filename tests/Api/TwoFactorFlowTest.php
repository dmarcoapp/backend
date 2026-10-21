<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User\User;
use App\Enum\User\TwoFactorMethod;
use App\Service\User\TwoFactor\TwoFactorSecretGenerator;
use App\Service\User\TwoFactor\TwoFactorService;

/**
 * @internal
 *
 * @coversNothing
 */
final class TwoFactorFlowTest extends ApiIntegrationTestCase
{
    public function testLoginRequiresTwoFactorCodeThenSucceeds(): void
    {
        $this->createAccountWithAuthenticator('twofactor-login@example.com');

        $initialResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/login_check',
            payload: [
                'username' => 'twofactor-login@example.com',
                'password' => 'Str0ngPassw0rd!@#',
            ],
        );

        self::assertSame(401, $initialResponse->getStatusCode(), (string) $initialResponse->getContent());
        $initialPayload = json_decode((string) $initialResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(401, $initialPayload['code'] ?? null);
        self::assertSame('two_factor_required', $initialPayload['error'] ?? null);

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'twofactor-login@example.com']);
        self::assertNotNull($user);

        $twoFactorService = self::getContainer()->get(TwoFactorService::class);
        $code = $twoFactorService->getAppCode($user);

        $loginResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/login_check',
            payload: [
                'username' => 'twofactor-login@example.com',
                'password' => 'Str0ngPassw0rd!@#',
                'two_factor_code' => $code,
            ],
        );

        self::assertSame(200, $loginResponse->getStatusCode(), (string) $loginResponse->getContent());
        $payload = json_decode((string) $loginResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNotEmpty($payload['token'] ?? null);
    }

    public function testRepeatedWrongTwoFactorCodesAreThrottled(): void
    {
        $this->createAccountWithAuthenticator('twofactor-bruteforce@example.com');

        // The limiter pool is on disk in this environment, so it outlives the run.
        self::getContainer()->get('limiter.two_factor')
            ->create('twofactor-bruteforce@example.com')
            ->reset()
        ;
        self::ensureKernelShutdown();

        // The password is accepted every time, so login throttling never counts
        // these as failures. Only the two-factor limiter stops them.
        $statusCodes = [];
        for ($attempt = 0; $attempt < 6; ++$attempt) {
            $response = $this->requestJson(
                method: 'POST',
                uri: '/v1/auth/login_check',
                payload: [
                    'username' => 'twofactor-bruteforce@example.com',
                    'password' => 'Str0ngPassw0rd!@#',
                    'two_factor_code' => '000000',
                ],
            );
            $statusCodes[] = $response->getStatusCode();
            self::ensureKernelShutdown();
        }

        self::assertSame([401, 401, 401, 401, 401, 429], $statusCodes);
    }

    public function testEnableAndDisableAppTwoFactor(): void
    {
        $this->createAccount('twofactor-app@example.com', 'Str0ngPassw0rd!@#');

        $token = $this->login('twofactor-app@example.com', 'Str0ngPassw0rd!@#');

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'twofactor-app@example.com']);
        self::assertNotNull($user);

        $twoFactorService = self::getContainer()->get(TwoFactorService::class);
        $appCode = $twoFactorService->getAppCode($user);

        $enableResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/user/profile/2fa/app/enable',
            payload: ['code' => $appCode],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $enableResponse->getStatusCode(), (string) $enableResponse->getContent());
        $enablePayload = json_decode((string) $enableResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('app', $enablePayload['twoFactorMethod'] ?? null);
        self::assertTrue($enablePayload['twoFactorAppEnabled'] ?? false);

        $profileResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/user/profile',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $profileResponse->getStatusCode(), (string) $profileResponse->getContent());
        $profilePayload = json_decode((string) $profileResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('twoFactorMethod', $profilePayload);
        self::assertArrayNotHasKey('twoFactorAppEnabled', $profilePayload);
        self::assertArrayNotHasKey('twoFactorAppSecret', $profilePayload);

        $twoFactorResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/user/profile/2fa',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $twoFactorResponse->getStatusCode(), (string) $twoFactorResponse->getContent());
        $twoFactorPayload = json_decode((string) $twoFactorResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('app', $twoFactorPayload['twoFactorMethod'] ?? null);
        self::assertTrue($twoFactorPayload['twoFactorAppEnabled'] ?? false);
        self::assertEmpty($twoFactorPayload['twoFactorAppSecret'] ?? null);

        $disableResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/user/profile/2fa/app/disable',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $disableResponse->getStatusCode(), (string) $disableResponse->getContent());
        $disablePayload = json_decode((string) $disableResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNull($disablePayload['twoFactorMethod'] ?? null);
        self::assertFalse($disablePayload['twoFactorAppEnabled'] ?? true);

        // Off again, so the password alone signs in.
        $loginResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/login_check',
            payload: [
                'username' => 'twofactor-app@example.com',
                'password' => 'Str0ngPassw0rd!@#',
            ],
        );
        self::assertSame(200, $loginResponse->getStatusCode(), (string) $loginResponse->getContent());
    }

    public function testEnableTwoFactorAppRejectsInvalidCode(): void
    {
        $this->createAccount('2fa-invalid@example.com', 'Str0ngPassw0rd!@#');

        $token = $this->login('2fa-invalid@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/user/profile/2fa/app/enable',
            payload: ['code' => '000000'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
    }

    private function createAccountWithAuthenticator(string $email): void
    {
        $user = $this->createAccount($email, 'Str0ngPassw0rd!@#');
        $user
            ->setTwoFactorMethod(TwoFactorMethod::APP)
            ->setTwoFactorAppSecret(self::getContainer()->get(TwoFactorSecretGenerator::class)->generate())
        ;
        $this->entityManager->flush();
    }
}
