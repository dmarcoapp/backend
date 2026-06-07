<?php

declare(strict_types=1);

namespace App\DTO\Input\DMARC\ReportXML;

final readonly class Row
{
    public function __construct(
        private string $source_ip,
        private int $count,
        private PolicyEvaluated $policy_evaluated,
    ) {}

    public function getSourceIp(): string
    {
        return $this->source_ip;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getPolicyEvaluated(): PolicyEvaluated
    {
        return $this->policy_evaluated;
    }
}
