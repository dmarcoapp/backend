<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User\User;
use App\Service\User\UserFactory;
use App\Tests\Api\ApiIntegrationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class UserFactoryTest extends ApiIntegrationTestCase
{
    public function testCreatePersistsAUserReadyToSignIn(): void
    {
        $factory = self::getContainer()->get(UserFactory::class);
        $user = $factory->create(
            email: 'factory@example.com',
            name: 'Factory User',
            plainPassword: 'Str0ngPassw0rd!@#',
        );

        self::assertNotNull($user->getId());
        self::assertNotNull($user->getSharedPostboxIdentifierToken());
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertNull($user->getTwoFactorMethod());

        $this->entityManager->clear();
        $storedUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'factory@example.com']);
        self::assertInstanceOf(User::class, $storedUser);
        self::assertSame('Factory User', $storedUser->getName());
    }
}
