<?php

declare(strict_types=1);

namespace App\DTO\Input\DMARC\ReportXML;

final readonly class ReportMetadata
{
    public function __construct(
        private string $org_name,
        private string $email,
        private ?string $extra_contact_info,
        private string $report_id,
        private DateRange $date_range,
    ) {}

    public function getOrgName(): string
    {
        return $this->org_name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getExtraContactInfo(): ?string
    {
        return $this->extra_contact_info;
    }

    public function getReportId(): string
    {
        return $this->report_id;
    }

    public function getDateRange(): DateRange
    {
        return $this->date_range;
    }
}
