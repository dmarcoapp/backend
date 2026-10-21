<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User\User;
use App\Repository\User\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\ByteString;

final readonly class UserFactory
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function create(
        string $email,
        string $name,
        string $plainPassword,
    ): User {
        do {
            $sharedPostboxIdentifierToken = ByteString::fromRandom(32, 'abcdefghijklmnopqrstuvwxyz0123456789')->toString();
        } while ($this->userRepository->findOneBy(['sharedPostboxIdentifierToken' => $sharedPostboxIdentifierToken]));

        $user = new User();
        $user
            ->setEmail($email)
            ->setName($name)
            ->setPassword($this->passwordHasher->hashPassword($user, $plainPassword))
            ->setRoles(['ROLE_USER'])
            ->setSharedPostboxIdentifierToken($sharedPostboxIdentifierToken)
        ;

        $this->userRepository->save($user);

        return $user;
    }
}
