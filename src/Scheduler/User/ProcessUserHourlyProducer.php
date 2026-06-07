<?php

declare(strict_types=1);

namespace App\Scheduler\User;

use App\Message\User\ProcessUserHourly;
use App\Repository\User\UserRepository;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;
use Symfony\Component\Uid\Uuid;

#[AsPeriodicTask(frequency: '1 hour', jitter: 60)]
final readonly class ProcessUserHourlyProducer
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
                new ProcessUserHourly($userId->toRfc4122()),
            );
        }
    }
}
