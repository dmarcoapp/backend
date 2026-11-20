<?php

declare(strict_types=1);

namespace App\MessageHandler\DMARC;

use App\Message\DMARC\DeleteExpiredReports;
use App\Repository\DMARC\ReportRepository;
use App\Service\Platform\PlatformSettingsProvider;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

// The message carries nothing, so it is named here rather than taken as an argument.
#[AsMessageHandler(handles: DeleteExpiredReports::class)]
final readonly class DeleteExpiredReportsHandler
{
    public function __construct(
        private ReportRepository $reportRepository,
        private PlatformSettingsProvider $settings,
    ) {}

    public function __invoke(): void
    {
        $retentionDays = $this->settings->reportRetentionDays();
        if ($retentionDays <= 0) {
            return;
        }

        $threshold = new \DateTimeImmutable(
            '-'.$retentionDays.' days',
            new \DateTimeZone('UTC'),
        );

        $this->reportRepository->deleteReportsOlderThan($threshold);
    }
}
