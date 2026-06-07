<?php

declare(strict_types=1);

namespace App\Scheduler\User;

use App\Message\User\ProcessUserDaily;
use App\Repository\User\UserRepository;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;
use Symfony\Component\Uid\Uuid;

#[AsCronTask(expression: '0 0 * * *', timezone: 'UTC')]
final readonly class ProcessUserDailyProducer
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
                new ProcessUserDaily($userId->toRfc4122()),
            );
        }
    }
}
