<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\User\UserFactory;
use App\Entity\User\User;
use App\Tests\Api\ApiIntegrationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class UserFactoryTest extends ApiIntegrationTestCase
{
    public function testCreatePersistsUserWithGeneratedTokens(): void
    {
        $factory = self::getContainer()->get(UserFactory::class);
        $user = $factory->create(
            email: 'factory@example.com',
            name: 'Factory User',
            plainPassword: 'Str0ngPassw0rd!@#',
            roles: ['ROLE_USER'],
        );

        self::assertNotNull($user->getId());
        self::assertNotNull($user->getEmailVerificationToken());
        self::assertNotNull($user->getSharedPostboxIdentifierToken());

        $this->entityManager->clear();
        $storedUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'factory@example.com']);
        self::assertInstanceOf(User::class, $storedUser);
        self::assertSame('Factory User', $storedUser->getName());
    }

    public function testCreateAssignsProvidedRoles(): void
    {
        $factory = self::getContainer()->get(UserFactory::class);
        $user = $factory->create(
            email: 'factory-roles@example.com',
            name: 'Factory Roles',
            plainPassword: 'Str0ngPassw0rd!@#',
            roles: ['ROLE_ADMIN'],
        );

        self::assertContains('ROLE_ADMIN', $user->getRoles());
    }
}
