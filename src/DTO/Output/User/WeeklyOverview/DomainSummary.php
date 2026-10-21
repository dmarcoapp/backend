<?php

declare(strict_types=1);

namespace App\DTO\Output\User\WeeklyOverview;

final readonly class DomainSummary
{
    private const array ENFORCING = ['quarantine', 'reject'];

    public function __construct(
        public string $domain,
        // Id of the domain's page in the dashboard, when DMARCo knows the domain.
        public ?string $domainId,
        // The p=, pct= and sp= policy published in the domain's latest report this week.
        public ?string $policy,
        public ?int $policyPercent,
        public ?string $subdomainPolicy,
        public bool $isNew,
        public bool $needsAttention,
        public TrafficTotals $traffic,
    ) {}

    /**
     * Whether the domain asks receivers to quarantine or reject all mail that
     * fails DMARC, its subdomains' included. Failing mail delivered anyway is
     * then the receiver's own call, such as for forwarded mail, and nothing
     * the owner can change.
     */
    public function isEnforced(): bool
    {
        return self::enforces($this->policy, $this->policyPercent, $this->subdomainPolicy);
    }

    public static function enforces(?string $policy, ?int $percent, ?string $subdomainPolicy): bool
    {
        return \in_array($policy, self::ENFORCING, true)
            && (null === $percent || $percent >= 100)
            && (null === $subdomainPolicy || \in_array($subdomainPolicy, self::ENFORCING, true));
    }

    /**
     * Whether the policy applies to only part of the failing mail (pct=).
     */
    public function coversPartOfMail(): bool
    {
        return \in_array($this->policy, self::ENFORCING, true)
            && null !== $this->policyPercent && $this->policyPercent < 100;
    }

    /**
     * Whether the policy enforces on the domain but not on its subdomains.
     */
    public function leavesSubdomainsOpen(): bool
    {
        return \in_array($this->policy, self::ENFORCING, true) && 'none' === $this->subdomainPolicy;
    }
}
