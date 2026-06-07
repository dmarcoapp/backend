<?php

declare(strict_types=1);

namespace App\Entity\User;

use App\Entity\DMARC\Domain;
use App\Entity\Email\Email;
use App\Entity\EntityInterface;
use App\Enum\User\TwoFactorMethod;
use App\Repository\User\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\UniqueConstraint(name: 'UNIQ_RESET_PASSWORD_TOKEN', fields: ['passwordResetToken'])]
#[ORM\UniqueConstraint(name: 'UNIQ_EMAIL_VERIFICATION_TOKEN', fields: ['emailVerificationToken'])]
#[ORM\UniqueConstraint(name: 'UNIQ_SHARED_POSTBOX_ID_TOKEN', fields: ['sharedPostboxIdentifierToken'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EntityInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 32)]
    private ?string $sharedPostboxIdentifierToken = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(enumType: TwoFactorMethod::class, options: ['default' => TwoFactorMethod::EMAIL->value])]
    private ?TwoFactorMethod $twoFactorMethod = TwoFactorMethod::EMAIL;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $twoFactorEmailSecret = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $twoFactorAppSecret = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordResetToken = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeInterface $passwordResetTokenExpiresAt = null;

    /**
     * @var Collection<int, Domain>
     */
    #[ORM\OneToMany(targetEntity: Domain::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $domains;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emailVerificationToken = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeInterface $emailVerificationTokenExpiresAt = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(options: ['default' => true])]
    private bool $unusualNewLoginNotificationEnabled = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $weeklyOverviewNotificationEnabled = true;

    /**
     * @var Collection<int, Email>
     */
    #[ORM\OneToMany(targetEntity: Email::class, mappedBy: 'owner', orphanRemoval: true)]
    private Collection $emails;

    /**
     * @var Collection<int, BlocklistEntry>
     */
    #[ORM\OneToMany(targetEntity: BlocklistEntry::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $blocklistEntries;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->domains = new ArrayCollection();
        $this->emails = new ArrayCollection();
        $this->blocklistEntries = new ArrayCollection();
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', (string) $this->password);

        return $data;
    }

    #[\Override]
    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    #[\Override]
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getSharedPostboxIdentifierToken(): string
    {
        if (null === $this->sharedPostboxIdentifierToken) {
            throw new \LogicException('Shared postbox identifier token is not initialized.');
        }

        return $this->sharedPostboxIdentifierToken;
    }

    public function setSharedPostboxIdentifierToken(string $sharedPostboxIdentifierToken): static
    {
        $this->sharedPostboxIdentifierToken = $sharedPostboxIdentifierToken;

        return $this;
    }

    /**
     * @see UserInterface
     */
    #[\Override]
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    #[\Override]
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getTwoFactorMethod(): TwoFactorMethod
    {
        return $this->twoFactorMethod ?? TwoFactorMethod::EMAIL;
    }

    public function setTwoFactorMethod(TwoFactorMethod $twoFactorMethod): static
    {
        $this->twoFactorMethod = $twoFactorMethod;

        return $this;
    }

    public function getTwoFactorEmailSecret(): ?string
    {
        return $this->twoFactorEmailSecret;
    }

    public function setTwoFactorEmailSecret(?string $twoFactorEmailSecret): static
    {
        $this->twoFactorEmailSecret = $twoFactorEmailSecret;

        return $this;
    }

    public function getTwoFactorAppSecret(): ?string
    {
        return $this->twoFactorAppSecret;
    }

    public function setTwoFactorAppSecret(?string $twoFactorAppSecret): static
    {
        $this->twoFactorAppSecret = $twoFactorAppSecret;

        return $this;
    }

    #[\Override]
    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }

    public function setPasswordResetToken(?string $passwordResetToken): static
    {
        $this->passwordResetToken = $passwordResetToken;

        return $this;
    }

    public function getPasswordResetTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->passwordResetTokenExpiresAt;
    }

    public function setPasswordResetTokenExpiresAt(?\DateTimeInterface $passwordResetTokenExpiresAt): static
    {
        $this->passwordResetTokenExpiresAt = $passwordResetTokenExpiresAt;

        return $this;
    }

    /**
     * @return Collection<int, Domain>
     */
    public function getDomains(): Collection
    {
        return $this->domains;
    }

    public function isVerified(): bool
    {
        return null === $this->emailVerificationToken;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function setEmailVerificationToken(?string $emailVerificationToken): static
    {
        $this->emailVerificationToken = $emailVerificationToken;

        return $this;
    }

    public function getEmailVerificationTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->emailVerificationTokenExpiresAt;
    }

    public function setEmailVerificationTokenExpiresAt(?\DateTimeInterface $emailVerificationTokenExpiresAt): static
    {
        $this->emailVerificationTokenExpiresAt = $emailVerificationTokenExpiresAt;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function isUnusualNewLoginNotificationEnabled(): bool
    {
        return $this->unusualNewLoginNotificationEnabled;
    }

    public function setUnusualNewLoginNotificationEnabled(bool $unusualNewLoginNotificationEnabled): static
    {
        $this->unusualNewLoginNotificationEnabled = $unusualNewLoginNotificationEnabled;

        return $this;
    }

    public function isWeeklyOverviewNotificationEnabled(): bool
    {
        return $this->weeklyOverviewNotificationEnabled;
    }

    public function setWeeklyOverviewNotificationEnabled(bool $weeklyOverviewNotificationEnabled): static
    {
        $this->weeklyOverviewNotificationEnabled = $weeklyOverviewNotificationEnabled;

        return $this;
    }

    /**
     * @return Collection<int, Email>
     */
    public function getEmails(): Collection
    {
        return $this->emails;
    }

    public function addEmail(Email $email): static
    {
        if (!$this->emails->contains($email)) {
            $this->emails->add($email);
            $email->setOwner($this);
        }

        return $this;
    }

    public function removeEmail(Email $email): static
    {
        if ($this->emails->removeElement($email)) {
            // set the owning side to null (unless already changed)
            if ($email->getOwner() === $this) {
                $email->setOwner(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, BlocklistEntry>
     */
    public function getBlocklistEntries(): Collection
    {
        return $this->blocklistEntries;
    }
}
