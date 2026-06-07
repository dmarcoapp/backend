<?php

declare(strict_types=1);

namespace App\DTO\Input\DMARC\ReportXML;

final readonly class Feedback
{
    public function __construct(
        private ReportMetadata $report_metadata,
        private PolicyPublished $policy_published,
        /** @var Record[] */
        private array $record = [],
    ) {}

    public function getReportMetadata(): ReportMetadata
    {
        return $this->report_metadata;
    }

    public function getPolicyPublished(): PolicyPublished
    {
        return $this->policy_published;
    }

    /** @return Record[] */
    public function getRecord(): array
    {
        return $this->record;
    }
}
