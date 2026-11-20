<?php

declare(strict_types=1);

namespace App\Service\User;

use App\DTO\Output\User\Dashboard;
use App\Helper\Date;
use App\Helper\Trend;
use App\Repository\DMARC\DomainRepository;
use App\Repository\DMARC\ReportRepository;

final readonly class DashboardMetricsProvider
{
    public function __construct(
        private ReportRepository $reportRepository,
        private DomainRepository $domainRepository,
    ) {}

    public function provide(int $periodDays): Dashboard
    {
        $currentPeriod = Date::createDatePeriod($periodDays);
        $previousPeriod = Date::createDatePeriod($periodDays, true);

        $reportCount = $this->reportRepository->getReportCountInDatePeriod($currentPeriod);
        $previousReportCount = $this->reportRepository->getReportCountInDatePeriod($previousPeriod);

        $messageCount = $this->reportRepository->getSumCountInDatePeriod($currentPeriod);
        $previousMessageCount = $this->reportRepository->getSumCountInDatePeriod($previousPeriod);

        $passRate = $this->reportRepository->getPassRate($currentPeriod);
        $previousPassRate = $this->reportRepository->getPassRate($previousPeriod);

        $threatsBlockedCount = $this->reportRepository->getThreatCount($currentPeriod);
        $previousThreatsBlockedCount = $this->reportRepository->getThreatCount($previousPeriod);

        $messageCountByDate = $this->reportRepository->getMessageCountByDate($currentPeriod);
        $threatCountByDate = $this->reportRepository->getThreatCountByDate($currentPeriod);

        [$complianceAverage] = $this->reportRepository->getComplianceAverage($currentPeriod);
        [$previousComplianceAverage] = $this->reportRepository->getComplianceAverage($previousPeriod);

        $newDomainCount = $this->domainRepository->getNewDomainCount($currentPeriod);
        $previousNewDomainCount = $this->domainRepository->getNewDomainCount($previousPeriod);
        $newDomains = $this->domainRepository->getNewDomains($currentPeriod);
        $topOffenders = $this->reportRepository->getOffendersInDatePeriod($currentPeriod);
        $topSenders = $this->reportRepository->getTopSendersInDatePeriod($currentPeriod);
        $sourceCountryDistribution = $this->reportRepository->getSourceCountryDistributionInDatePeriod($currentPeriod);
        $topReportingOrganizations = $this->reportRepository->getTopReportingOrganizationsInDatePeriod($currentPeriod);

        return new Dashboard(
            reportCount: $reportCount,
            reportTrend: Trend::calculate((float) $reportCount, (float) $previousReportCount),
            messageCount: $messageCount,
            messageTrend: Trend::calculate((float) $messageCount, (float) $previousMessageCount),
            passRate: $passRate,
            passTrend: Trend::calculate($passRate, $previousPassRate),
            threatsBlockedCount: $threatsBlockedCount,
            threatsBlockedTrend: Trend::calculate((float) $threatsBlockedCount, (float) $previousThreatsBlockedCount),
            messageCountByDate: $messageCountByDate,
            threatCountByDate: $threatCountByDate,
            compliance: [
                'dmarc' => (float) $complianceAverage['dmarc'],
                'dkim' => (float) $complianceAverage['dkim'],
                'spf' => (float) $complianceAverage['spf'],
            ],
            complianceTrend: [
                'dmarc' => Trend::calculate((float) $complianceAverage['dmarc'], (float) $previousComplianceAverage['dmarc']),
                'dkim' => Trend::calculate((float) $complianceAverage['dkim'], (float) $previousComplianceAverage['dkim']),
                'spf' => Trend::calculate((float) $complianceAverage['spf'], (float) $previousComplianceAverage['spf']),
            ],
            newDomainCount: $newDomainCount,
            newDomainTrend: Trend::calculate((float) $newDomainCount, (float) $previousNewDomainCount),
            newDomains: $newDomains,
            topOffenders: $topOffenders,
            topSenders: $topSenders,
            sourceCountryDistribution: $sourceCountryDistribution,
            topReportingOrganizations: $topReportingOrganizations,
        );
    }
}
