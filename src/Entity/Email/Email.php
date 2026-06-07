<?php

declare(strict_types=1);

namespace App\Entity\Email;

use App\Entity\DMARC\Report;
use App\Entity\User\User;
use App\Repository\Email\EmailRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EmailRepository::class)]
#[ORM\Index(name: 'idx_email_message_id_address', fields: ['messageId', 'toAddress'])]
class Email
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255)]
    private ?string $fromAddress = null;

    #[ORM\Column(length: 255)]
    private ?string $toAddress = null;

    #[ORM\Column(length: 255)]
    private ?string $messageId = null;

    #[ORM\Column(length: 255)]
    private ?string $attachmentBucket = null;

    #[ORM\Column(length: 255)]
    private ?string $attachmentKey = null;

    #[ORM\Column(length: 255)]
    private ?string $attachmentFilename = null;

    #[ORM\Column(length: 255)]
    private ?string $attachmentContentType = null;

    #[ORM\OneToOne(mappedBy: 'email', cascade: ['persist', 'remove'])]
    private ?Report $report = null;

    #[ORM\ManyToOne(inversedBy: 'emails')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    public function getId(): ?Uuid
    {
        return $this->id;
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

    public function getFromAddress(): ?string
    {
        return $this->fromAddress;
    }

    public function setFromAddress(string $fromAddress): static
    {
        $this->fromAddress = $fromAddress;

        return $this;
    }

    public function getToAddress(): ?string
    {
        return $this->toAddress;
    }

    public function setToAddress(string $toAddress): static
    {
        $this->toAddress = $toAddress;

        return $this;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function setMessageId(string $messageId): static
    {
        $this->messageId = $messageId;

        return $this;
    }

    public function getAttachmentBucket(): ?string
    {
        return $this->attachmentBucket;
    }

    public function setAttachmentBucket(string $attachmentBucket): static
    {
        $this->attachmentBucket = $attachmentBucket;

        return $this;
    }

    public function getAttachmentKey(): ?string
    {
        return $this->attachmentKey;
    }

    public function setAttachmentKey(string $attachmentKey): static
    {
        $this->attachmentKey = $attachmentKey;

        return $this;
    }

    public function getAttachmentFilename(): ?string
    {
        return $this->attachmentFilename;
    }

    public function setAttachmentFilename(string $attachmentFilename): static
    {
        $this->attachmentFilename = $attachmentFilename;

        return $this;
    }

    public function getAttachmentContentType(): ?string
    {
        return $this->attachmentContentType;
    }

    public function setAttachmentContentType(string $attachmentContentType): static
    {
        $this->attachmentContentType = $attachmentContentType;

        return $this;
    }

    public function getReport(): ?Report
    {
        return $this->report;
    }

    public function setReport(?Report $report): static
    {
        // unset the owning side of the relation if necessary
        if (null === $report && null !== $this->report) {
            $this->report->setEmail(null);
        }

        // set the owning side of the relation if necessary
        if (null !== $report && $report->getEmail() !== $this) {
            $report->setEmail($this);
        }

        $this->report = $report;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }
}
