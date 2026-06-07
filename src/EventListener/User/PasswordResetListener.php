<?php

declare(strict_types=1);

namespace App\EventListener\User;

use App\Entity\User\User;
use App\Event\User\PasswordResetEvent;
use App\Repository\User\UserRepository;
use App\Service\User\PasswordResetMailer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\String\ByteString;

#[AsEventListener]
final readonly class PasswordResetListener
{
    public function __construct(
        private UserRepository $userRepository,
        private PasswordResetMailer $passwordResetMailer,
        #[Autowire(param: 'app.auth.user.reset_password.token.ttl_seconds')]
        private int $passwordResetTokenLifetime,
        #[Autowire(param: 'app.auth.user.reset_password.token.random_bytes')]
        private int $passwordResetTokenLength,
    ) {}

    public function __invoke(PasswordResetEvent $event): void
    {
        $user = $event->getUser();

        $this->generateAndSavePasswordResetToken($user);

        $this->passwordResetMailer->send($user);
    }

    public function generateAndSavePasswordResetToken(User $user): void
    {
        $oldPasswordResetTokenExpiresAt = $user->getPasswordResetTokenExpiresAt();
        $passwordResetTokenExpiresAt = new \DateTimeImmutable('+'.$this->passwordResetTokenLifetime.' seconds');

        if ($oldPasswordResetTokenExpiresAt
            && abs($oldPasswordResetTokenExpiresAt->getTimestamp() - $passwordResetTokenExpiresAt->getTimestamp()) < 300
        ) {
            return; // Don't generate a new token if it's within 5 minutes of the old one
        }

        do {
            $passwordResetToken = ByteString::fromRandom($this->passwordResetTokenLength)->toString();
        } while ($this->userRepository->findOneBy(['passwordResetToken' => $passwordResetToken]));

        $user
            ->setPasswordResetToken($passwordResetToken)
            ->setPasswordResetTokenExpiresAt($passwordResetTokenExpiresAt)
        ;

        $this->userRepository->save($user);
    }
}
