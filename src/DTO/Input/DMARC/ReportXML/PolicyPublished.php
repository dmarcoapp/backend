<?php

declare(strict_types=1);

namespace App\DTO\Input\DMARC\ReportXML;

final readonly class PolicyPublished
{
    public function __construct(
        private string $domain,
        private ?string $adkim,
        private ?string $aspf,
        private ?string $p,
        private ?string $sp,
        private ?int $pct,
        private ?string $np,
    ) {}

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getAdkim(): ?string
    {
        return $this->adkim;
    }

    public function getAspf(): ?string
    {
        return $this->aspf;
    }

    public function getP(): ?string
    {
        return $this->p;
    }

    public function getSp(): ?string
    {
        return $this->sp;
    }

    public function getPct(): ?int
    {
        return $this->pct;
    }

    public function getNp(): ?string
    {
        return $this->np;
    }
}
