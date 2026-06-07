<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User\User;
use App\Repository\User\UserRepository;
use Psr\Log\LoggerInterface;

final readonly class UserDeleteProcessor
{
    public function __construct(
        private UserRepository $userRepository,
        private LoggerInterface $logger,
    ) {}

    public function process(User $user): void
    {
        try {
            $this->userRepository->delete($user);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to delete user.', [
                'email' => $user->getEmail(),
                'exception' => $e,
                'user_id' => $user->getId()?->toRfc4122(),
            ]);

            throw new \RuntimeException('Failed to delete user.', previous: $e);
        }
    }
}
