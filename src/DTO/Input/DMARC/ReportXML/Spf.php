<?php

declare(strict_types=1);

namespace App\DTO\Input\DMARC\ReportXML;

final readonly class Spf
{
    public function __construct(
        private string $domain,
        private string $result,
    ) {}

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getResult(): string
    {
        return $this->result;
    }
}
