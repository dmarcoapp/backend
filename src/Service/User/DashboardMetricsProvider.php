<?php

declare(strict_types=1);

namespace App\Service\User;

use App\DTO\Output\User\Dashboard;
use App\Entity\User\User;
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

    public function provide(User $user, int $periodDays): Dashboard
    {
        $currentPeriod = Date::createDatePeriod($periodDays);
        $previousPeriod = Date::createDatePeriod($periodDays, true);

        $reportCount = $this->reportRepository->getReportCountInDatePeriod($currentPeriod, $user);
        $previousReportCount = $this->reportRepository->getReportCountInDatePeriod($previousPeriod, $user);

        $messageCount = $this->reportRepository->getSumCountInDatePeriod($currentPeriod, $user);
        $previousMessageCount = $this->reportRepository->getSumCountInDatePeriod($previousPeriod, $user);

        $passRate = $this->reportRepository->getPassRate($currentPeriod, $user);
        $previousPassRate = $this->reportRepository->getPassRate($previousPeriod, $user);

        $threatsBlockedCount = $this->reportRepository->getThreatCount($currentPeriod, $user);
        $previousThreatsBlockedCount = $this->reportRepository->getThreatCount($previousPeriod, $user);

        $messageCountByDate = $this->reportRepository->getMessageCountByDate($currentPeriod, $user);
        $threatCountByDate = $this->reportRepository->getThreatCountByDate($currentPeriod, $user);

        [$complianceAverage] = $this->reportRepository->getComplianceAverage($currentPeriod, $user);
        [$previousComplianceAverage] = $this->reportRepository->getComplianceAverage($previousPeriod, $user);

        $newDomainCount = $this->domainRepository->getNewDomainCount($currentPeriod, $user);
        $previousNewDomainCount = $this->domainRepository->getNewDomainCount($previousPeriod, $user);
        $newDomains = $this->domainRepository->getNewDomains($currentPeriod, $user);
        $topOffenders = $this->reportRepository->getOffendersInDatePeriod($currentPeriod, $user);
        $topSenders = $this->reportRepository->getTopSendersInDatePeriod($currentPeriod, $user);
        $sourceCountryDistribution = $this->reportRepository->getSourceCountryDistributionInDatePeriod($currentPeriod, $user);
        $topReportingOrganizations = $this->reportRepository->getTopReportingOrganizationsInDatePeriod($currentPeriod, $user);

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
