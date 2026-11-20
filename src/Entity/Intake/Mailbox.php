<?php

declare(strict_types=1);

namespace App\Entity\Intake;

use App\Enum\Intake\AfterImport;
use App\Enum\Intake\MailboxAuthMethod;
use App\Enum\Intake\MailboxSecurity;
use App\Repository\Intake\MailboxRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A mailbox DMARCo fetches reports from over IMAP.
 */
#[ORM\Entity(repositoryClass: MailboxRepository::class)]
#[ORM\Table(name: 'mailbox')]
class Mailbox
{
    public const int DEFAULT_POLL_INTERVAL_MINUTES = 15;
    public const int DEFAULT_FIRST_IMPORT_DAYS = 30;

    /**
     * Known before the row is saved, because the credentials are encrypted
     * for this mailbox alone.
     */
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $host;

    #[ORM\Column]
    private int $port;

    #[ORM\Column(length: 16, enumType: MailboxSecurity::class)]
    private MailboxSecurity $security;

    #[ORM\Column(length: 255)]
    private string $username;

    #[ORM\Column(length: 16, enumType: MailboxAuthMethod::class)]
    private MailboxAuthMethod $authMethod = MailboxAuthMethod::PASSWORD;

    /**
     * JSON of what signing in takes, encrypted with SecretBox under
     * credentialsContext().
     */
    #[ORM\Column(type: Types::TEXT)]
    private string $credentials = '';

    #[ORM\Column(length: 255)]
    private string $folder = 'INBOX';

    /**
     * The address in the rua= of the DMARC records that lands here.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reportAddress = null;

    #[ORM\Column(length: 16, enumType: AfterImport::class)]
    private AfterImport $afterImport = AfterImport::MARK_SEEN;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $moveToFolder = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(options: ['default' => self::DEFAULT_POLL_INTERVAL_MINUTES])]
    private int $pollIntervalMinutes = self::DEFAULT_POLL_INTERVAL_MINUTES;

    #[ORM\Column(options: ['default' => self::DEFAULT_FIRST_IMPORT_DAYS])]
    private int $firstImportDays = self::DEFAULT_FIRST_IMPORT_DAYS;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private int|string|null $uidValidity = null;

    /**
     * The highest UID looked at, whether it held a report or not.
     */
    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private int|string|null $lastUid = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastPolledAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSuccessAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $consecutiveFailures = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name, string $host, MailboxSecurity $security, string $username)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->host = $host;
        $this->security = $security;
        $this->port = $security->defaultPort();
        $this->username = $username;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function credentialsContext(): string
    {
        return 'mailbox:credentials:'.$this->id->toRfc4122();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(string $host): static
    {
        $this->host = $host;

        return $this;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function setPort(int $port): static
    {
        $this->port = $port;

        return $this;
    }

    public function getSecurity(): MailboxSecurity
    {
        return $this->security;
    }

    public function setSecurity(MailboxSecurity $security): static
    {
        $this->security = $security;

        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getAuthMethod(): MailboxAuthMethod
    {
        return $this->authMethod;
    }

    public function getEncryptedCredentials(): string
    {
        return $this->credentials;
    }

    public function setEncryptedCredentials(MailboxAuthMethod $authMethod, string $credentials): static
    {
        $this->authMethod = $authMethod;
        $this->credentials = $credentials;

        return $this;
    }

    public function getFolder(): string
    {
        return $this->folder;
    }

    public function setFolder(string $folder): static
    {
        $this->folder = $folder;

        return $this;
    }

    public function getReportAddress(): ?string
    {
        return $this->reportAddress;
    }

    public function setReportAddress(?string $reportAddress): static
    {
        $this->reportAddress = $reportAddress;

        return $this;
    }

    public function getAfterImport(): AfterImport
    {
        return $this->afterImport;
    }

    public function getMoveToFolder(): ?string
    {
        return $this->moveToFolder;
    }

    public function setAfterImport(AfterImport $afterImport, ?string $moveToFolder = null): static
    {
        $this->afterImport = $afterImport;
        $this->moveToFolder = AfterImport::MOVE === $afterImport ? $moveToFolder : null;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getPollIntervalMinutes(): int
    {
        return $this->pollIntervalMinutes;
    }

    public function setPollIntervalMinutes(int $pollIntervalMinutes): static
    {
        $this->pollIntervalMinutes = $pollIntervalMinutes;

        return $this;
    }

    public function getFirstImportDays(): int
    {
        return $this->firstImportDays;
    }

    public function setFirstImportDays(int $firstImportDays): static
    {
        $this->firstImportDays = $firstImportDays;

        return $this;
    }

    public function getUidValidity(): ?int
    {
        return null === $this->uidValidity ? null : (int) $this->uidValidity;
    }

    public function getLastUid(): ?int
    {
        return null === $this->lastUid ? null : (int) $this->lastUid;
    }

    /**
     * Where reading continues next time. A new UIDVALIDITY means the server
     * numbered the folder anew, so the old position means nothing.
     */
    public function setCursor(?int $uidValidity, ?int $lastUid): static
    {
        $this->uidValidity = $uidValidity;
        $this->lastUid = $lastUid;

        return $this;
    }

    public function getLastPolledAt(): ?\DateTimeImmutable
    {
        return $this->lastPolledAt;
    }

    public function getLastSuccessAt(): ?\DateTimeImmutable
    {
        return $this->lastSuccessAt;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getConsecutiveFailures(): int
    {
        return $this->consecutiveFailures;
    }

    public function recordSuccess(\DateTimeImmutable $at): static
    {
        $this->lastPolledAt = $at;
        $this->lastSuccessAt = $at;
        $this->lastError = null;
        $this->consecutiveFailures = 0;

        return $this;
    }

    public function recordFailure(\DateTimeImmutable $at, string $error): static
    {
        $this->lastPolledAt = $at;
        $this->lastError = $error;
        ++$this->consecutiveFailures;

        return $this;
    }

    /**
     * Whether the mailbox is due another look. After five failures in a row it
     * is tried only hourly, so a wrong password is not retried every few
     * minutes against a server that may lock the account for it.
     */
    public function isDue(\DateTimeImmutable $now): bool
    {
        if (!$this->enabled) {
            return false;
        }

        if (null === $this->lastPolledAt) {
            return true;
        }

        $interval = $this->consecutiveFailures >= 5 ? max(60, $this->pollIntervalMinutes) : $this->pollIntervalMinutes;

        return $this->lastPolledAt->modify(sprintf('+%d minutes', $interval)) <= $now;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
