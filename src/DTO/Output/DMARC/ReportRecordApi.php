<?php

declare(strict_types=1);

namespace App\DTO\Output\DMARC;

use App\Enum\DMARC\DispositionType;
use App\Enum\DMARC\DKIMAlign;
use App\Enum\DMARC\SPFAlign;
use App\Enum\DMARC\SPFResult;
use Symfony\Component\Uid\Uuid;

final class ReportRecordApi
{
    public Uuid $id;
    public ?Uuid $reportId;
    public ?string $sourceIp;
    public ?IpInfoApi $sourceIpInfo;
    public ?int $count;
    public ?DispositionType $disposition;
    public ?DKIMAlign $dkimAlign;
    public ?SPFAlign $spfAlign;
    public ?string $dkimAuth;
    public ?string $dkimDomain;
    public ?string $dkimSelector;
    public ?SPFResult $spfAuth;
    public ?string $spfDomain;

    public function __construct(Uuid $id)
    {
        $this->id = $id;
    }
}
