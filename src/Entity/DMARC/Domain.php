<?php

declare(strict_types=1);

namespace App\Entity\DMARC;

use App\Entity\EntityInterface;
use App\Entity\FilterableInterface;
use App\Entity\SortableInterface;
use App\Entity\User\User;
use App\Enum\DMARC\Domain\ProtectionLevel;
use App\Repository\DMARC\DomainRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DomainRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_USER_DOMAIN', fields: ['user', 'domain'])]
class Domain implements EntityInterface, FilterableInterface, SortableInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'domains')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 255)]
    private string $domain;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastChecked = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $dmarcRecord = null;

    #[ORM\Column(nullable: true, enumType: ProtectionLevel::class)]
    private ?ProtectionLevel $protectionLevel = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isConfiguredCorrectly = null;

    // TODO: $dmarcRecordTTL

    public function __construct(User $user, string $domain)
    {
        $this->user = $user;
        $this->domain = $domain;
        $this->createdAt = new \DateTimeImmutable();
    }

    #[\Override]
    public static function getAvailableFilterFields(): array
    {
        return ['domain'];
    }

    #[\Override]
    public static function getAvailableSortFields(): array
    {
        return ['domain', 'lastChecked', 'protectionLevel', 'createdAt'];
    }

    #[\Override]
    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getLastChecked(): ?\DateTimeImmutable
    {
        return $this->lastChecked;
    }

    public function setLastChecked(?\DateTimeImmutable $lastChecked): static
    {
        $this->lastChecked = $lastChecked;

        return $this;
    }

    public function getDmarcRecord(): ?string
    {
        return $this->dmarcRecord;
    }

    public function setDmarcRecord(?string $dmarcRecord): static
    {
        $this->dmarcRecord = $dmarcRecord;

        return $this;
    }

    public function getProtectionLevel(): ?ProtectionLevel
    {
        return $this->protectionLevel;
    }

    public function setProtectionLevel(?ProtectionLevel $protectionLevel): static
    {
        $this->protectionLevel = $protectionLevel;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function isConfiguredCorrectly(): ?bool
    {
        return $this->isConfiguredCorrectly;
    }

    public function setIsConfiguredCorrectly(?bool $isConfiguredCorrectly): static
    {
        $this->isConfiguredCorrectly = $isConfiguredCorrectly;

        return $this;
    }
}
