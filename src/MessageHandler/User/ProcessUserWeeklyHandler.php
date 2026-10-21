<?php

declare(strict_types=1);

namespace App\MessageHandler\User;

use App\Entity\User\User;
use App\Message\User\ProcessUserWeekly;
use App\Repository\User\UserRepository;
use App\Service\User\DMARC\WeeklyOverviewMailer;
use App\Service\User\DMARC\WeeklyOverviewProvider;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProcessUserWeeklyHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private WeeklyOverviewProvider $weeklyOverviewProvider,
        private WeeklyOverviewMailer $weeklyOverviewMailer,
    ) {}

    public function __invoke(ProcessUserWeekly $message): void
    {
        $user = $this->userRepository->find($message->userId);
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isWeeklyOverviewNotificationEnabled()) {
            return;
        }

        $oneWeekAgo = new \DateTimeImmutable('-7 days', new \DateTimeZone('UTC'));
        if ($user->getCreatedAt() > $oneWeekAgo) {
            return;
        }

        $this->weeklyOverviewMailer->send($user, $this->weeklyOverviewProvider->provide($user));
    }
}
