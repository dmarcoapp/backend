<?php

declare(strict_types=1);

namespace App\DTO\Output\DMARC;

use App\Enum\DMARC\AlignmentType;
use App\Enum\DMARC\DispositionType;
use Symfony\Component\Uid\Uuid;

final class ReportApi
{
    public Uuid $id;
    public ?\DateTimeInterface $receivedAt;
    public ?string $fromAddress;
    public ?string $reportingOrganization;
    public ?string $reportingOrganizationEmail;
    public ?string $reportingOrganizationExtraContact;
    public ?string $reportId;
    public ?bool $isVerified;
    public ?\DateTimeInterface $beginDate;
    public ?\DateTimeInterface $endDate;
    public ?string $domain;
    public ?string $domainId;
    public ?AlignmentType $adkimPolicy;
    public ?AlignmentType $aspfPolicy;
    public ?DispositionType $pPolicy;
    public ?DispositionType $spPolicy;
    public ?int $pctPolicy;
    public ?DispositionType $npPolicy;
    public ?int $sumCount;
    public ?float $dmarcCompliance;
    public ?float $spfCompliance;
    public ?float $dkimCompliance;

    public function __construct(Uuid $id)
    {
        $this->id = $id;
    }
}
