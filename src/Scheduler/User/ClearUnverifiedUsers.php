<?php

declare(strict_types=1);

namespace App\Scheduler\User;

use App\Repository\User\UserRepository;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask(frequency: '1 day', jitter: 3600)]
final readonly class ClearUnverifiedUsers
{
    public function __construct(private UserRepository $userRepository) {}

    public function __invoke(): void
    {
        $ids = $this->userRepository->getUnverifiedUserIdsToDelete();

        foreach (array_chunk($ids, 100) as $chunk) {
            $this->userRepository->deleteByIds($chunk);
        }
    }
}
