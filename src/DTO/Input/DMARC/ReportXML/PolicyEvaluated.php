<?php

declare(strict_types=1);

namespace App\DTO\Input\DMARC\ReportXML;

final readonly class PolicyEvaluated
{
    public function __construct(
        private string $disposition,
        private string $dkim,
        private string $spf,
    ) {}

    public function getDisposition(): string
    {
        return $this->disposition;
    }

    public function getDkim(): string
    {
        return $this->dkim;
    }

    public function getSpf(): string
    {
        return $this->spf;
    }
}
