<?php

declare(strict_types=1);

namespace App\MessageHandler\User;

use App\Entity\User\User;
use App\Helper\Date;
use App\Message\User\ProcessUserWeekly;
use App\Repository\User\UserRepository;
use App\Service\User\DashboardMetricsProvider;
use App\Service\User\DMARC\WeeklyOverviewMailer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProcessUserWeeklyHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private DashboardMetricsProvider $dashboardMetricsProvider,
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

        $weeklyPeriod = Date::createDatePeriod(7);
        $dashboard = $this->dashboardMetricsProvider->provide($user, 7);
        $periodEnd = $weeklyPeriod->getEndDate() ?? $weeklyPeriod->getStartDate();

        $this->weeklyOverviewMailer->send(
            user: $user,
            dashboard: $dashboard,
            periodStart: $weeklyPeriod->getStartDate(),
            periodEnd: \DateTimeImmutable::createFromInterface($periodEnd)->sub(new \DateInterval('P1D')),
        );
    }
}
