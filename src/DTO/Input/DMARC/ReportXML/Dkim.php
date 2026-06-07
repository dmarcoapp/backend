<?php

declare(strict_types=1);

namespace App\DTO\Input\DMARC\ReportXML;

final readonly class Dkim
{
    public function __construct(
        private string $domain,
        private string $result,
        private ?string $selector,
    ) {}

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getResult(): string
    {
        return $this->result;
    }

    public function getSelector(): ?string
    {
        return $this->selector;
    }
}
