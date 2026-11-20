<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User\User;
use App\Repository\User\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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
        $user = new User();
        $user
            ->setEmail($email)
            ->setName($name)
            ->setPassword($this->passwordHasher->hashPassword($user, $plainPassword))
        ;

        $this->userRepository->save($user);

        return $user;
    }
}
