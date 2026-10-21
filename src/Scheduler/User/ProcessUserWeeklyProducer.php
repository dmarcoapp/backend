<?php

declare(strict_types=1);

namespace App\Scheduler\User;

use App\Message\User\ProcessUserWeekly;
use App\Repository\User\UserRepository;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;
use Symfony\Component\Uid\Uuid;

// At 06:00 on Tuesday. The overview covers the week up to Sunday, and the
// reports for a day arrive during the next one, so Monday is too early.
#[AsCronTask(expression: '0 6 * * 2', timezone: 'UTC')]
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
