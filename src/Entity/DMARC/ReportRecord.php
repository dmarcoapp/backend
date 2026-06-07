<?php

declare(strict_types=1);

namespace App\Entity\DMARC;

use App\Entity\EntityInterface;
use App\Entity\FilterableInterface;
use App\Entity\SortableInterface;
use App\Enum\DMARC\DispositionType;
use App\Enum\DMARC\DKIMAlign;
use App\Enum\DMARC\SPFAlign;
use App\Enum\DMARC\SPFResult;
use App\Repository\DMARC\ReportRecordRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ReportRecordRepository::class)]
class ReportRecord implements EntityInterface, FilterableInterface, SortableInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'records')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Report $report = null;

    #[ORM\Column(length: 255)]
    private ?string $sourceIp = null;

    #[ORM\Embedded(class: IpInfo::class)]
    private ?IpInfo $sourceIpInfo = null;

    #[ORM\Column]
    private ?int $count = null;

    #[ORM\Column(enumType: DispositionType::class)]
    private ?DispositionType $disposition = null;

    #[ORM\Column(enumType: DKIMAlign::class)]
    private ?DKIMAlign $dkimAlign = null;

    #[ORM\Column(enumType: SPFAlign::class)]
    private ?SPFAlign $spfAlign = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dkimAuth = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dkimDomain = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dkimSelector = null;

    #[ORM\Column(enumType: SPFResult::class)]
    private ?SPFResult $spfAuth = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $spfDomain = null;

    #[\Override]
    public static function getAvailableFilterFields(): array
    {
        return ['sourceIp', 'count', 'disposition'];
    }

    #[\Override]
    public static function getAvailableSortFields(): array
    {
        return ['sourceIp', 'count'];
    }

    #[\Override]
    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getReport(): ?Report
    {
        return $this->report;
    }

    public function setReport(?Report $report): static
    {
        $this->report = $report;

        return $this;
    }

    public function getSourceIp(): ?string
    {
        return $this->sourceIp;
    }

    public function setSourceIp(string $sourceIp): static
    {
        $this->sourceIp = $sourceIp;

        return $this;
    }

    public function getSourceIpInfo(): ?IpInfo
    {
        return $this->sourceIpInfo;
    }

    public function setSourceIpInfo(?IpInfo $sourceIpInfo): static
    {
        $this->sourceIpInfo = $sourceIpInfo;

        return $this;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(int $count): static
    {
        $this->count = $count;

        return $this;
    }

    public function getDisposition(): ?DispositionType
    {
        return $this->disposition;
    }

    public function setDisposition(DispositionType $disposition): static
    {
        $this->disposition = $disposition;

        return $this;
    }

    public function getDkimAlign(): ?DKIMAlign
    {
        return $this->dkimAlign;
    }

    public function setDkimAlign(DKIMAlign $dkimAlign): static
    {
        $this->dkimAlign = $dkimAlign;

        return $this;
    }

    public function getSpfAlign(): ?SPFAlign
    {
        return $this->spfAlign;
    }

    public function setSpfAlign(SPFAlign $spfAlign): static
    {
        $this->spfAlign = $spfAlign;

        return $this;
    }

    public function getDkimAuth(): ?string
    {
        return $this->dkimAuth;
    }

    public function setDkimAuth(?string $dkimAuth): static
    {
        $this->dkimAuth = $dkimAuth;

        return $this;
    }

    public function getDkimDomain(): ?string
    {
        return $this->dkimDomain;
    }

    public function setDkimDomain(?string $dkimDomain): static
    {
        $this->dkimDomain = $dkimDomain;

        return $this;
    }

    public function getDkimSelector(): ?string
    {
        return $this->dkimSelector;
    }

    public function setDkimSelector(?string $dkimSelector): static
    {
        $this->dkimSelector = $dkimSelector;

        return $this;
    }

    public function getSpfAuth(): ?SPFResult
    {
        return $this->spfAuth;
    }

    public function setSpfAuth(?SPFResult $spfAuth): static
    {
        $this->spfAuth = $spfAuth;

        return $this;
    }

    public function getSpfDomain(): ?string
    {
        return $this->spfDomain;
    }

    public function setSpfDomain(?string $spfDomain): static
    {
        $this->spfDomain = $spfDomain;

        return $this;
    }
}
