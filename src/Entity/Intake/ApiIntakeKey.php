<?php

declare(strict_types=1);

namespace App\Entity\Intake;

use App\Repository\Intake\ApiIntakeKeyRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A key that uploads reports through the API. Only a hash of its secret is
 * kept, so the key is shown once, when it is made.
 */
#[ORM\Entity(repositoryClass: ApiIntakeKeyRepository::class)]
#[ORM\Table(name: 'api_intake_key')]
#[ORM\UniqueConstraint(name: 'uniq_api_intake_key_lookup', fields: ['lookupId'])]
class ApiIntakeKey
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 100)]
    private string $name;

    /**
     * The public part of the key, which finds it without a scan.
     */
    #[ORM\Column(length: 16)]
    private string $lookupId;

    /**
     * SHA-256 of the secret part. The secret is long and random, so a fast
     * hash keeps it as safe as a slow one would.
     */
    #[ORM\Column(length: 64)]
    private string $secretHash;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct(string $name, string $lookupId, string $secretHash)
    {
        $this->name = $name;
        $this->lookupId = $lookupId;
        $this->secretHash = $secretHash;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLookupId(): string
    {
        return $this->lookupId;
    }

    public function getSecretHash(): string
    {
        return $this->secretHash;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(\DateTimeImmutable $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;

        return $this;
    }
}
