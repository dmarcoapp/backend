<?php

declare(strict_types=1);

namespace App\Mapper\Platform;

use App\DTO\Output\Platform\PlatformSettingsApi;
use App\Entity\Platform\PlatformSettings;
use App\Service\Email\AggregateReportPostboxAddressProvider;
use App\Service\Platform\PlatformSettingsProvider;
use Symfonycasts\MicroMapper\AsMapper;
use Symfonycasts\MicroMapper\MapperInterface;

#[AsMapper(from: PlatformSettings::class, to: PlatformSettingsApi::class)]
final readonly class PlatformSettingsEntityToApiMapper implements MapperInterface
{
    public function __construct(
        private PlatformSettingsProvider $settings,
        private AggregateReportPostboxAddressProvider $reportAddressProvider,
    ) {}

    #[\Override]
    public function load(object $from, string $toClass, array $context): PlatformSettingsApi
    {
        assert($from instanceof PlatformSettings);

        return new PlatformSettingsApi();
    }

    #[\Override]
    public function populate(object $from, object $to, array $context): object
    {
        $entity = $from;
        $dto = $to;

        assert($entity instanceof PlatformSettings);
        assert($dto instanceof PlatformSettingsApi);

        $legacy = $this->settings->isLegacyMode();

        $dto->mode = $legacy ? PlatformSettingsApi::MODE_LEGACY : PlatformSettingsApi::MODE_PLATFORM;
        $dto->reportRetentionDays = $this->settings->reportRetentionDays();
        $dto->ipLookupEnabled = $this->settings->ipLookupEnabled();
        $dto->effectiveSenderAddress = $this->settings->senderAddress();
        $dto->defaultSenderAddress = $this->settings->defaultSenderAddress();
        $dto->reportAddress = $this->reportAddressProvider->getAddress();
        $dto->reportAddressDomain = $this->settings->reportAddressDomain();
        $dto->apiIntakeEnabled = $this->settings->isApiIntakeEnabled();
        $dto->uploadEndpointEnabled = !$legacy && true === $entity->isUploadEndpointEnabled();
        $dto->webhookEnabled = $legacy ? $dto->apiIntakeEnabled : true === $entity->isWebhookEnabled();
        $dto->webhookSecretSet = $legacy
            ? null !== $this->settings->webhookSecret()
            : null !== $entity->getEncryptedWebhookSecret();
        $dto->unusedEnvironment = $this->settings->unusedEnvironment();
        $dto->importedFromEnvironment = $entity->getImportedFromEnvironment();

        // The environment's mail server is not shown, because its DSN carries
        // the password.
        if ($legacy) {
            $dto->mailConfigured = $this->isLegacyMailConfigured();

            return $dto;
        }

        $dto->mailConfigured = null !== $entity->getSmtpHost();
        $dto->smtpHost = $entity->getSmtpHost();
        $dto->smtpPort = $entity->getSmtpPort() ?? $entity->getSmtpSecurity()?->defaultPort();
        $dto->smtpSecurity = $entity->getSmtpSecurity()?->value;
        $dto->smtpUsername = $entity->getSmtpUsername();
        $dto->smtpPasswordSet = null !== $entity->getEncryptedSmtpPassword();
        $dto->smtpPasswordUnreadable = $dto->smtpPasswordSet && !$this->settings->isSmtpPasswordReadable();
        $dto->senderAddress = $entity->getSenderAddress();

        return $dto;
    }

    private function isLegacyMailConfigured(): bool
    {
        try {
            return $this->settings->isMailConfigured();
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
