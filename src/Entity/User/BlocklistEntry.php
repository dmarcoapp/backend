<?php

declare(strict_types=1);

namespace App\Entity\User;

use App\Entity\EntityInterface;
use App\Entity\FilterableInterface;
use App\Entity\SortableInterface;
use App\Repository\User\BlocklistEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: BlocklistEntryRepository::class)]
#[ORM\Table(name: 'email_blocklist_entry')]
#[ORM\UniqueConstraint(name: 'uniq_user_blocklist_pattern', fields: ['user', 'pattern'])]
#[ORM\Index(name: 'idx_blocklist_user', fields: ['user'])]
class BlocklistEntry implements EntityInterface, FilterableInterface, SortableInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'blocklistEntries')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 255)]
    private string $pattern;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, string $pattern)
    {
        $this->user = $user;
        $this->pattern = $pattern;
        $this->createdAt = new \DateTimeImmutable();
    }

    #[\Override]
    public static function getAvailableFilterFields(): array
    {
        return ['pattern', 'createdAt'];
    }

    #[\Override]
    public static function getAvailableSortFields(): array
    {
        return ['pattern', 'createdAt'];
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

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function setPattern(string $pattern): static
    {
        $this->pattern = $pattern;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
