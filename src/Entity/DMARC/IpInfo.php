<?php

declare(strict_types=1);

namespace App\Entity\DMARC;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class IpInfo
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $orgName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $orgCountry = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $orgAbuseEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $orgTechEmail = null;

    public function getOrgName(): ?string
    {
        return $this->orgName;
    }

    public function setOrgName(?string $orgName): static
    {
        $this->orgName = $orgName;

        return $this;
    }

    public function getOrgCountry(): ?string
    {
        return $this->orgCountry;
    }

    public function setOrgCountry(?string $orgCountry): static
    {
        $this->orgCountry = $orgCountry;

        return $this;
    }

    public function getOrgAbuseEmail(): ?string
    {
        return $this->orgAbuseEmail;
    }

    public function setOrgAbuseEmail(?string $orgAbuseEmail): static
    {
        $this->orgAbuseEmail = $orgAbuseEmail;

        return $this;
    }

    public function getOrgTechEmail(): ?string
    {
        return $this->orgTechEmail;
    }

    public function setOrgTechEmail(?string $orgTechEmail): static
    {
        $this->orgTechEmail = $orgTechEmail;

        return $this;
    }
}
