<?php

declare(strict_types=1);

namespace App\DTO\Input\DMARC\ReportXML;

final readonly class AuthResults
{
    /**
     * @param Dkim[] $dkim
     * @param Spf[]  $spf
     */
    public function __construct(
        private ?array $dkim,
        private array $spf,
    ) {}

    /** @return null|Dkim[] */
    public function getDkim(): ?array
    {
        return $this->dkim;
    }

    /** @return Spf[] */
    public function getSpf(): array
    {
        return $this->spf;
    }
}
