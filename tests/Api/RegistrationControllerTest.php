<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\String\ByteString;

/**
 * @internal
 *
 * @coversNothing
 */
final class RegistrationControllerTest extends ApiIntegrationTestCase
{
    public function testRegisterReturnsValidationError(): void
    {
        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/register',
            payload: [
                'email' => 'not-an-email',
                'name' => '',
                'password' => 'short',
            ],
        );

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testRegisterRejectsExistingUser(): void
    {
        $this->createVerifiedUser('existing@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/register',
            payload: [
                'email' => 'existing@example.com',
                'name' => 'Already There',
                'password' => 'Str0ngPassw0rd!@#',
            ],
        );

        self::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testRegisterReturnsTooManyRequestsWhenApiLimited(): void
    {
        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/register',
            payload: [
                'email' => 'limited@example.com',
                'name' => 'Limited',
                'password' => 'Str0ngPassw0rd!@#',
            ],
            configureContainer: function (ContainerInterface $container): void {
                $container->set('limiter.anonymous_api', $this->mockRejectedLimiterFactory());
            },
        );

        self::assertSame(429, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testVerifyRejectsExpiredToken(): void
    {
        $user = $this->createVerifiedUser('expired-verify@example.com', 'Str0ngPassw0rd!@#');
        $user
            ->setEmailVerificationToken(ByteString::fromRandom(32)->toString())
            ->setEmailVerificationTokenExpiresAt(new \DateTimeImmutable('-1 hour'))
        ;
        $this->entityManager->flush();

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/verify',
            payload: [
                'email' => 'expired-verify@example.com',
                'token' => $user->getEmailVerificationToken(),
            ],
        );

        self::assertSame(401, $response->getStatusCode(), (string) $response->getContent());

        $this->entityManager->clear();
        $reloaded = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'expired-verify@example.com']);
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getEmailVerificationToken());
    }
}
