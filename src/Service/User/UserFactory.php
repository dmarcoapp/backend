<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User\User;
use App\Enum\User\TwoFactorMethod;
use App\Event\User\UserCreatedEvent;
use App\Repository\User\UserRepository;
use App\Service\User\TwoFactor\TwoFactorSecretGenerator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\ByteString;

final readonly class UserFactory
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EventDispatcherInterface $eventDispatcher,
        private TwoFactorSecretGenerator $twoFactorSecretGenerator,
        #[Autowire(param: 'app.auth.user.email_verification.token.ttl_seconds')]
        private int $emailVerificationTokenLifetime,
        #[Autowire(param: 'app.auth.user.email_verification.token.random_bytes')]
        private int $emailVerificationTokenLength,
    ) {}

    public function create(
        string $email,
        string $name,
        string $plainPassword,
        array $roles
    ): User {
        $emailVerificationTokenExpiresAt = new \DateTimeImmutable('+'.$this->emailVerificationTokenLifetime.' seconds');

        do {
            $emailVerificationToken = ByteString::fromRandom($this->emailVerificationTokenLength)->toString();
        } while ($this->userRepository->findOneBy(['emailVerificationToken' => $emailVerificationToken]));

        do {
            $sharedPostboxIdentifierToken = ByteString::fromRandom(32, 'abcdefghijklmnopqrstuvwxyz0123456789')->toString();
        } while ($this->userRepository->findOneBy(['sharedPostboxIdentifierToken' => $sharedPostboxIdentifierToken]));

        $user = new User();
        $user
            ->setEmail($email)
            ->setName($name)
            ->setPassword($this->passwordHasher->hashPassword($user, $plainPassword))
            ->setRoles($roles)
            ->setTwoFactorMethod(TwoFactorMethod::EMAIL)
            ->setTwoFactorEmailSecret($this->twoFactorSecretGenerator->generate())
            ->setEmailVerificationToken($emailVerificationToken)
            ->setEmailVerificationTokenExpiresAt($emailVerificationTokenExpiresAt)
            ->setSharedPostboxIdentifierToken($sharedPostboxIdentifierToken)
        ;

        $this->userRepository->save($user);
        $this->eventDispatcher->dispatch(new UserCreatedEvent($user));

        return $user;
    }
}
