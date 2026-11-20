<?php

declare(strict_types=1);

namespace App\Entity\Platform;

use App\Enum\Platform\SmtpSecurity;
use App\Repository\Platform\PlatformSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The settings of the installation that are not infrastructure, kept in one
 * row. A null column means the setting was never made and its default applies.
 */
#[ORM\Entity(repositoryClass: PlatformSettingsRepository::class)]
#[ORM\Table(name: 'platform_settings')]
class PlatformSettings
{
    public const int ID = 1;
    public const string SMTP_PASSWORD_CONTEXT = 'platform_settings:smtp_password:1';
    public const string GATEWAY_SECRET_CONTEXT = 'platform_settings:gateway_secret:1';

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::ID;

    /**
     * The local part of the address reports are sent to.
     */
    #[ORM\Column(length: 32)]
    private string $reportAddressToken;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reportAddressDomain = null;

    #[ORM\Column(nullable: true)]
    private ?int $reportRetentionDays = null;

    #[ORM\Column(nullable: true)]
    private ?bool $ipLookupEnabled = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $smtpHost = null;

    #[ORM\Column(nullable: true)]
    private ?int $smtpPort = null;

    #[ORM\Column(length: 16, nullable: true, enumType: SmtpSecurity::class)]
    private ?SmtpSecurity $smtpSecurity = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $smtpUsername = null;

    /**
     * Encrypted with SecretBox under SMTP_PASSWORD_CONTEXT.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $smtpPassword = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $senderAddress = null;

    /**
     * Whether reports may arrive through the API, from a mail gateway or with
     * an API key. Off unless switched on.
     */
    #[ORM\Column(nullable: true)]
    private ?bool $apiIntakeEnabled = null;

    /**
     * The secret a mail gateway signs its webhooks with, encrypted with
     * SecretBox under GATEWAY_SECRET_CONTEXT.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $gatewaySecret = null;

    /**
     * When each environment variable was taken over, by name.
     *
     * @var array<string, string>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    private array $importedFromEnvironment = [];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(string $reportAddressToken)
    {
        $this->reportAddressToken = $reportAddressToken;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getReportAddressToken(): string
    {
        return $this->reportAddressToken;
    }

    public function getReportAddressDomain(): ?string
    {
        return $this->reportAddressDomain;
    }

    public function setReportAddressDomain(?string $reportAddressDomain): static
    {
        $this->reportAddressDomain = $reportAddressDomain;

        return $this->touch();
    }

    public function getReportRetentionDays(): ?int
    {
        return $this->reportRetentionDays;
    }

    public function setReportRetentionDays(?int $reportRetentionDays): static
    {
        $this->reportRetentionDays = $reportRetentionDays;

        return $this->touch();
    }

    public function isIpLookupEnabled(): ?bool
    {
        return $this->ipLookupEnabled;
    }

    public function setIpLookupEnabled(?bool $ipLookupEnabled): static
    {
        $this->ipLookupEnabled = $ipLookupEnabled;

        return $this->touch();
    }

    public function getSmtpHost(): ?string
    {
        return $this->smtpHost;
    }

    public function setSmtpHost(?string $smtpHost): static
    {
        $this->smtpHost = $smtpHost;

        return $this->touch();
    }

    public function getSmtpPort(): ?int
    {
        return $this->smtpPort;
    }

    public function setSmtpPort(?int $smtpPort): static
    {
        $this->smtpPort = $smtpPort;

        return $this->touch();
    }

    public function getSmtpSecurity(): ?SmtpSecurity
    {
        return $this->smtpSecurity;
    }

    public function setSmtpSecurity(?SmtpSecurity $smtpSecurity): static
    {
        $this->smtpSecurity = $smtpSecurity;

        return $this->touch();
    }

    public function getSmtpUsername(): ?string
    {
        return $this->smtpUsername;
    }

    public function setSmtpUsername(?string $smtpUsername): static
    {
        $this->smtpUsername = $smtpUsername;

        return $this->touch();
    }

    public function getEncryptedSmtpPassword(): ?string
    {
        return $this->smtpPassword;
    }

    public function setEncryptedSmtpPassword(?string $smtpPassword): static
    {
        $this->smtpPassword = $smtpPassword;

        return $this->touch();
    }

    /**
     * Forgets the outgoing mail server, which turns outgoing mail off.
     */
    public function clearSmtp(): static
    {
        $this->smtpHost = null;
        $this->smtpPort = null;
        $this->smtpSecurity = null;
        $this->smtpUsername = null;
        $this->smtpPassword = null;

        return $this->touch();
    }

    public function getSenderAddress(): ?string
    {
        return $this->senderAddress;
    }

    public function setSenderAddress(?string $senderAddress): static
    {
        $this->senderAddress = $senderAddress;

        return $this->touch();
    }

    public function isApiIntakeEnabled(): ?bool
    {
        return $this->apiIntakeEnabled;
    }

    public function setApiIntakeEnabled(?bool $apiIntakeEnabled): static
    {
        $this->apiIntakeEnabled = $apiIntakeEnabled;

        return $this->touch();
    }

    public function getEncryptedGatewaySecret(): ?string
    {
        return $this->gatewaySecret;
    }

    public function setEncryptedGatewaySecret(?string $gatewaySecret): static
    {
        $this->gatewaySecret = $gatewaySecret;

        return $this->touch();
    }

    /**
     * @return array<string, string>
     */
    public function getImportedFromEnvironment(): array
    {
        return $this->importedFromEnvironment;
    }

    public function markImportedFromEnvironment(string $variable, \DateTimeImmutable $at): static
    {
        $this->importedFromEnvironment[$variable] = $at->format(\DATE_ATOM);
        ksort($this->importedFromEnvironment);

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this;
    }
}
