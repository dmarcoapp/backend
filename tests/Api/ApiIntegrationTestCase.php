<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User\User;
use App\Enum\User\TwoFactorMethod;
use App\Service\User\TwoFactor\TwoFactorSecretGenerator;
use App\Service\User\TwoFactor\TwoFactorService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\ByteString;

abstract class ApiIntegrationTestCase extends ApiTestCase
{
    protected EntityManagerInterface $entityManager;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $dbPath = \dirname(__DIR__, 2).'/var/test.db';
        if (is_file($dbPath)) {
            @unlink($dbPath);
        }

        self::bootKernel();
        $entityManager = self::getContainer()->get('doctrine')->getManager();

        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        if ($metadata) {
            $schemaTool->dropSchema($metadata);
            $schemaTool->createSchema($metadata);
        }

        self::ensureKernelShutdown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
    }

    protected function createVerifiedUser(string $email, string $password, string $name = 'Test User'): User
    {
        $user = new User();
        $user
            ->setEmail($email)
            ->setName($name)
            ->setRoles(['ROLE_USER'])
            ->setSharedPostboxIdentifierToken(ByteString::fromRandom(32, 'abcdefghijklmnopqrstuvwxyz0123456789')->toString())
            ->setEmailVerificationToken(null)
            ->setEmailVerificationTokenExpiresAt(null)
            ->setTwoFactorMethod(TwoFactorMethod::EMAIL)
            ->setTwoFactorEmailSecret(self::getContainer()->get(TwoFactorSecretGenerator::class)->generate())
        ;

        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    protected function authorizeHeaders(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    protected function login(string $email, string $password): string
    {
        $payload = $this->loginPayload($email, $password);
        self::assertArrayHasKey('token', $payload);

        return $payload['token'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function loginPayload(string $email, string $password): array
    {
        $initialResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/login_check',
            payload: [
                'username' => $email,
                'password' => $password,
            ],
        );

        self::assertSame(401, $initialResponse->getStatusCode(), (string) $initialResponse->getContent());
        $initialPayload = json_decode((string) $initialResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(401, $initialPayload['code'] ?? null);
        self::assertSame('two_factor_required', $initialPayload['error'] ?? null);

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);

        $twoFactorService = self::getContainer()->get(TwoFactorService::class);
        $code = TwoFactorMethod::APP === $twoFactorService->getMethod($user)
            ? $twoFactorService->getAppCode($user)
            : $twoFactorService->getEmailCode($user);

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/auth/login_check',
            payload: [
                'username' => $email,
                'password' => $password,
                'two_factor_code' => $code,
            ],
        );

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
