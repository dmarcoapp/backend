<?php

declare(strict_types=1);

namespace App\DTO\Input\DMARC\ReportXML;

final readonly class DateRange
{
    public function __construct(
        private int $begin,
        private int $end,
    ) {}

    public function getBegin(): int
    {
        return $this->begin;
    }

    public function getEnd(): int
    {
        return $this->end;
    }
}
