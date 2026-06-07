<?php

declare(strict_types=1);

namespace App\MessageHandler\User;

use App\Message\User\ProcessUserDaily;
use App\Repository\DMARC\ReportRepository;
use App\Repository\User\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProcessUserDailyHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private ReportRepository $reportRepository,
        #[Autowire(env: 'int:APP_REPORT_RETENTION_DAYS')]
        private int $reportRetentionDays,
    ) {}

    public function __invoke(ProcessUserDaily $message): void
    {
        $user = $this->userRepository->find($message->userId);

        if (!$user) {
            return;
        }

        if ($this->reportRetentionDays <= 0) {
            return;
        }

        $threshold = new \DateTimeImmutable(
            '-'.$this->reportRetentionDays.' days',
            new \DateTimeZone('UTC'),
        );

        $this->reportRepository->deleteReportsForUserOlderThan($user, $threshold);
    }
}
