<?php

declare(strict_types=1);

namespace App\Entity\DMARC;

use App\Entity\Email\Email;
use App\Entity\EntityInterface;
use App\Entity\FilterableInterface;
use App\Entity\SortableInterface;
use App\Enum\DMARC\AlignmentType;
use App\Enum\DMARC\DispositionType;
use App\Repository\DMARC\ReportRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ReportRepository::class)]
class Report implements EntityInterface, FilterableInterface, SortableInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rawXML = null;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $isProcessed = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reportingOrganization = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reportingOrganizationEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reportingOrganizationExtraContact = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reportId = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $beginDate = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $domain = null;

    #[ORM\Column(nullable: true, enumType: AlignmentType::class)]
    private ?AlignmentType $adkimPolicy = null;

    #[ORM\Column(nullable: true, enumType: AlignmentType::class)]
    private ?AlignmentType $aspfPolicy = null;

    #[ORM\Column(nullable: true, enumType: DispositionType::class)]
    private ?DispositionType $pPolicy = null;

    #[ORM\Column(nullable: true, enumType: DispositionType::class)]
    private ?DispositionType $spPolicy = null;

    #[ORM\Column(nullable: true)]
    private ?int $pctPolicy = null;

    #[ORM\Column(nullable: true, enumType: DispositionType::class)]
    private ?DispositionType $npPolicy = null;

    /**
     * @var Collection<int, ReportRecord>
     */
    #[ORM\OneToMany(targetEntity: ReportRecord::class, mappedBy: 'report', orphanRemoval: true)]
    private Collection $records;

    #[ORM\Column(nullable: true)]
    private ?int $sumCount = null;

    #[ORM\Column(nullable: true)]
    private ?float $dmarcCompliance = null;

    #[ORM\Column(nullable: true)]
    private ?float $spfCompliance = null;

    #[ORM\Column(nullable: true)]
    private ?float $dkimCompliance = null;

    #[ORM\OneToOne(inversedBy: 'report', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Email $email = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isVerified = null;

    public function __construct()
    {
        $this->records = new ArrayCollection();
    }

    #[\Override]
    public static function getAvailableFilterFields(): array
    {
        return [
            'reportingOrganization',
            'domain',
            'beginDate',
            'endDate',
            'dmarcCompliance',
            'spfCompliance',
            'dkimCompliance',
            'sumCount',
            'isVerified',
            'records.sourceIp',
        ];
    }

    #[\Override]
    public static function getAvailableSortFields(): array
    {
        return [
            'reportingOrganization',
            'domain',
            'beginDate',
            'endDate',
            'dmarcCompliance',
            'spfCompliance',
            'dkimCompliance',
            'sumCount',
        ];
    }

    #[\Override]
    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getRawXML(): ?string
    {
        return $this->rawXML;
    }

    public function setRawXML(?string $rawXML): static
    {
        $this->rawXML = $rawXML;

        return $this;
    }

    public function isProcessed(): ?bool
    {
        return $this->isProcessed;
    }

    public function setIsProcessed(bool $isProcessed): static
    {
        $this->isProcessed = $isProcessed;

        return $this;
    }

    public function getReportingOrganization(): ?string
    {
        return $this->reportingOrganization;
    }

    public function setReportingOrganization(?string $reportingOrganization): static
    {
        $this->reportingOrganization = $reportingOrganization;

        return $this;
    }

    public function getReportingOrganizationEmail(): ?string
    {
        return $this->reportingOrganizationEmail;
    }

    public function setReportingOrganizationEmail(?string $reportingOrganizationEmail): static
    {
        $this->reportingOrganizationEmail = $reportingOrganizationEmail;

        return $this;
    }

    public function getReportingOrganizationExtraContact(): ?string
    {
        return $this->reportingOrganizationExtraContact;
    }

    public function setReportingOrganizationExtraContact(?string $reportingOrganizationExtraContact): static
    {
        $this->reportingOrganizationExtraContact = $reportingOrganizationExtraContact;

        return $this;
    }

    public function getReportId(): ?string
    {
        return $this->reportId;
    }

    public function setReportId(?string $reportId): static
    {
        $this->reportId = $reportId;

        return $this;
    }

    public function getBeginDate(): ?\DateTimeImmutable
    {
        return $this->beginDate;
    }

    public function setBeginDate(?\DateTimeImmutable $beginDate): static
    {
        $this->beginDate = $beginDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(?string $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    public function getAdkimPolicy(): ?AlignmentType
    {
        return $this->adkimPolicy;
    }

    public function setAdkimPolicy(?AlignmentType $adkimPolicy): static
    {
        $this->adkimPolicy = $adkimPolicy;

        return $this;
    }

    public function getAspfPolicy(): ?AlignmentType
    {
        return $this->aspfPolicy;
    }

    public function setAspfPolicy(?AlignmentType $aspfPolicy): static
    {
        $this->aspfPolicy = $aspfPolicy;

        return $this;
    }

    public function getPPolicy(): ?DispositionType
    {
        return $this->pPolicy;
    }

    public function setPPolicy(?DispositionType $pPolicy): static
    {
        $this->pPolicy = $pPolicy;

        return $this;
    }

    public function getSpPolicy(): ?DispositionType
    {
        return $this->spPolicy;
    }

    public function setSpPolicy(?DispositionType $spPolicy): static
    {
        $this->spPolicy = $spPolicy;

        return $this;
    }

    public function getPctPolicy(): ?int
    {
        return $this->pctPolicy;
    }

    public function setPctPolicy(?int $pctPolicy): static
    {
        $this->pctPolicy = $pctPolicy;

        return $this;
    }

    public function getNpPolicy(): ?DispositionType
    {
        return $this->npPolicy;
    }

    public function setNpPolicy(?DispositionType $npPolicy): static
    {
        $this->npPolicy = $npPolicy;

        return $this;
    }

    /**
     * @return Collection<int, ReportRecord>
     */
    public function getRecords(): Collection
    {
        return $this->records;
    }

    public function addRecord(ReportRecord $record): static
    {
        if (!$this->records->contains($record)) {
            $this->records->add($record);
            $record->setReport($this);
        }

        return $this;
    }

    public function removeRecord(ReportRecord $record): static
    {
        if ($this->records->removeElement($record)) {
            // set the owning side to null (unless already changed)
            if ($record->getReport() === $this) {
                $record->setReport(null);
            }
        }

        return $this;
    }

    public function getSumCount(): ?int
    {
        return $this->sumCount;
    }

    public function setSumCount(?int $sumCount): static
    {
        $this->sumCount = $sumCount;

        return $this;
    }

    public function getDmarcCompliance(): ?float
    {
        return $this->dmarcCompliance;
    }

    public function setDmarcCompliance(?float $dmarcCompliance): static
    {
        $this->dmarcCompliance = $dmarcCompliance;

        return $this;
    }

    public function getSpfCompliance(): ?float
    {
        return $this->spfCompliance;
    }

    public function setSpfCompliance(?float $spfCompliance): static
    {
        $this->spfCompliance = $spfCompliance;

        return $this;
    }

    public function getDkimCompliance(): ?float
    {
        return $this->dkimCompliance;
    }

    public function setDkimCompliance(?float $dkimCompliance): static
    {
        $this->dkimCompliance = $dkimCompliance;

        return $this;
    }

    public function getEmail(): ?Email
    {
        return $this->email;
    }

    public function setEmail(?Email $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(?bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }
}
