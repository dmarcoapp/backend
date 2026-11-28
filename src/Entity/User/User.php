<?php

declare(strict_types=1);

namespace App\Entity\User;

use App\Entity\EntityInterface;
use App\Enum\User\TwoFactorMethod;
use App\Repository\User\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EntityInterface
{
    // DMARCo has one account, and it may do everything there is to do.
    public const string ROLE = 'ROLE_USER';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    /**
     * Null while two-factor authentication is off.
     */
    #[ORM\Column(nullable: true, enumType: TwoFactorMethod::class)]
    private ?TwoFactorMethod $twoFactorMethod = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $twoFactorAppSecret = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(options: ['default' => true])]
    private bool $unusualNewLoginNotificationEnabled = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $weeklyOverviewNotificationEnabled = true;

    /**
     * Goes up whenever every session of the account is to end. Each access
     * token carries the value it was issued under, see SessionVersionClaim.
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $sessionVersion = 0;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
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

    /**
     * @see UserInterface
     */
    #[\Override]
    public function getRoles(): array
    {
        return [self::ROLE];
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

    public function getTwoFactorMethod(): ?TwoFactorMethod
    {
        return $this->twoFactorMethod;
    }

    public function setTwoFactorMethod(?TwoFactorMethod $twoFactorMethod): static
    {
        $this->twoFactorMethod = $twoFactorMethod;

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

    public function getSessionVersion(): int
    {
        return $this->sessionVersion;
    }

    public function endAllSessions(): static
    {
        ++$this->sessionVersion;

        return $this;
    }

    #[\Override]
    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
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
}
