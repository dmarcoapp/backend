<?php

declare(strict_types=1);

namespace App\DTO\Input\DMARC\ReportXML;

final readonly class Record
{
    public function __construct(
        private Row $row,
        private Identifiers $identifiers,
        private AuthResults $auth_results,
    ) {}

    public function getRow(): Row
    {
        return $this->row;
    }

    public function getIdentifiers(): Identifiers
    {
        return $this->identifiers;
    }

    public function getAuthResults(): AuthResults
    {
        return $this->auth_results;
    }
}
