<?php

declare(strict_types=1);

namespace App\DTO\Output\User;

final class Dashboard
{
    public function __construct(
        public int $reportCount,
        public float $reportTrend,
        public int $messageCount,
        public float $messageTrend,
        public float $passRate,
        public float $passTrend,
        public int $threatsBlockedCount,
        public float $threatsBlockedTrend,
        public array $messageCountByDate,
        public array $threatCountByDate,
        public array $compliance,
        public array $complianceTrend,
        public int $newDomainCount,
        public float $newDomainTrend,
        public array $newDomains,
        public array $topOffenders,
        public array $topSenders,
        public array $sourceCountryDistribution,
        public array $topReportingOrganizations,
    ) {}
}
