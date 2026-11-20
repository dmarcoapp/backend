<?php

declare(strict_types=1);

namespace App\MessageHandler\User;

use App\Message\User\SendWeeklyOverview;
use App\Repository\User\UserRepository;
use App\Service\User\DMARC\WeeklyOverviewMailer;
use App\Service\User\DMARC\WeeklyOverviewProvider;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

// The message carries nothing, so it is named here rather than taken as an argument.
#[AsMessageHandler(handles: SendWeeklyOverview::class)]
final readonly class SendWeeklyOverviewHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private WeeklyOverviewProvider $weeklyOverviewProvider,
        private WeeklyOverviewMailer $weeklyOverviewMailer,
    ) {}

    public function __invoke(): void
    {
        $account = $this->userRepository->findAccount();
        if (null === $account) {
            return;
        }

        if (!$account->isWeeklyOverviewNotificationEnabled()) {
            return;
        }

        // An account younger than a week has no full week to look back on.
        $oneWeekAgo = new \DateTimeImmutable('-7 days', new \DateTimeZone('UTC'));
        if ($account->getCreatedAt() > $oneWeekAgo) {
            return;
        }

        $this->weeklyOverviewMailer->send($account, $this->weeklyOverviewProvider->provide());
    }
}
