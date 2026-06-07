<?php

declare(strict_types=1);

namespace App\DTO\Input\DMARC\ReportXML;

final readonly class Identifiers
{
    public function __construct(private string $header_from) {}

    public function getHeaderFrom(): string
    {
        return $this->header_from;
    }
}
