<?php

declare(strict_types=1);

namespace App\Scheduler\User;

use App\Message\User\ProcessUserWeekly;
use App\Repository\User\UserRepository;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;
use Symfony\Component\Uid\Uuid;

// At 00:01 on Monday.
#[AsCronTask(expression: '1 0 * * 1', timezone: 'UTC')]
final readonly class ProcessUserWeeklyProducer
{
    public function __construct(
        private UserRepository $userRepository,
        private MessageBusInterface $messageBus,
    ) {}

    public function __invoke(): void
    {
        $users = $this->userRepository->findAll();

        foreach ($users as $user) {
            $userId = $user->getId();
            if (!$userId instanceof Uuid) {
                continue;
            }

            $this->messageBus->dispatch(
                new ProcessUserWeekly($userId->toRfc4122()),
            );
        }
    }
}
