<?php

declare(strict_types=1);

namespace App\Service\User\TwoFactor;

use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TwoFactorSecretManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TwoFactorSecretGenerator $secretGenerator,
    ) {}

    public function ensureEmailSecret(User $user): string
    {
        $secret = $user->getTwoFactorEmailSecret();
        if (null !== $secret) {
            return $secret;
        }

        $secret = $this->secretGenerator->generate();
        $user->setTwoFactorEmailSecret($secret);
        $this->entityManager->flush();

        return $secret;
    }

    public function ensureAppSecret(User $user): string
    {
        $secret = $user->getTwoFactorAppSecret();
        if (null !== $secret) {
            return $secret;
        }

        $secret = $this->secretGenerator->generate();
        $user->setTwoFactorAppSecret($secret);
        $this->entityManager->flush();

        return $secret;
    }
}
