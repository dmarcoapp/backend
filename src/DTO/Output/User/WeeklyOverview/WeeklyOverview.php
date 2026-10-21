<?php

declare(strict_types=1);

namespace App\DTO\Output\User\WeeklyOverview;

/**
 * Everything the weekly overview email shows, for one user and one week.
 */
final readonly class WeeklyOverview
{
    /**
     * @param list<string>        $reportingOrganizations
     * @param list<DomainSummary> $domains                the ones needing attention first, then by volume
     * @param list<Finding>       $findings               the most important first
     */
    public function __construct(
        public \DateTimeImmutable $periodStart,
        // Last day of the period, inclusive.
        public \DateTimeImmutable $periodEnd,
        public \DateTimeImmutable $previousPeriodStart,
        public \DateTimeImmutable $previousPeriodEnd,
        public int $reportCount,
        public array $reportingOrganizations,
        public TrafficTotals $traffic,
        public TrafficTotals $previousTraffic,
        public array $domains,
        public array $findings,
        // Where the user's DMARC records should send reports.
        public string $reportAddress,
    ) {}

    public function hasReports(): bool
    {
        return $this->reportCount > 0;
    }

    /**
     * @return list<DomainSummary>
     */
    public function domainsNeedingAttention(): array
    {
        return array_values(array_filter(
            $this->domains,
            static fn (DomainSummary $domain): bool => $domain->needsAttention,
        ));
    }

    /**
     * Change in message volume against the previous week, in percent, or null
     * when the previous week had no messages to compare with.
     */
    public function messagesChangePercent(): ?float
    {
        if (0 === $this->previousTraffic->messages) {
            return null;
        }

        return ($this->traffic->messages - $this->previousTraffic->messages) / $this->previousTraffic->messages * 100;
    }

    /**
     * Change in the DMARC pass rate against the previous week, in percentage
     * points, or null when either week had no messages.
     */
    public function passRateChangePoints(): ?float
    {
        $current = $this->traffic->passRate();
        $previous = $this->previousTraffic->passRate();
        if (null === $current || null === $previous) {
            return null;
        }

        return ($current - $previous) * 100;
    }

    /**
     * The period in a short form, such as "Oct 14–20" or "Sep 28–Oct 4".
     */
    public function periodLabel(bool $withYear = false): string
    {
        return self::formatRange($this->periodStart, $this->periodEnd, $withYear);
    }

    public function previousPeriodLabel(): string
    {
        return self::formatRange($this->previousPeriodStart, $this->previousPeriodEnd, false);
    }

    private static function formatRange(\DateTimeImmutable $start, \DateTimeImmutable $end, bool $withYear): string
    {
        $sameYear = $start->format('Y') === $end->format('Y');
        if (!$sameYear) {
            return $start->format('M j, Y').'–'.$end->format('M j, Y');
        }

        $range = $start->format('m') === $end->format('m')
            ? $start->format('M j').'–'.$end->format('j')
            : $start->format('M j').'–'.$end->format('M j');

        return $withYear ? $range.', '.$end->format('Y') : $range;
    }
}
