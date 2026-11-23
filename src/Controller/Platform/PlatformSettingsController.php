<?php

declare(strict_types=1);

namespace App\Controller\Platform;

use App\DTO\Input\Platform\PlatformSettingsUpdate;
use App\DTO\Output\Platform\PlatformSettingsApi;
use App\DTO\Output\Platform\TestEmailResultApi;
use App\Entity\User\User;
use App\Enum\Platform\SmtpSecurity;
use App\Repository\Platform\PlatformSettingsRepository;
use App\Service\Platform\PlatformSettingsProvider;
use App\Service\Platform\TestEmailSender;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfonycasts\MicroMapper\MicroMapperInterface;

#[IsGranted(User::ROLE)]
final class PlatformSettingsController extends AbstractController
{
    public function __construct(
        private readonly PlatformSettingsProvider $settings,
        private readonly PlatformSettingsRepository $repository,
        private readonly TestEmailSender $testEmailSender,
        private readonly MicroMapperInterface $microMapper,
    ) {}

    #[OA\Get(
        summary: 'Gets the platform settings. Passwords are never included.',
        security: [['Bearer' => []]],
    )]
    #[OA\Response(
        response: 200,
        description: 'The platform settings.',
        content: new Model(type: PlatformSettingsApi::class)
    )]
    #[OA\Tag(name: 'Platform')]
    #[Route('/v1/platform/settings', name: 'app_platform_settings_get', methods: [Request::METHOD_GET])]
    public function get(): JsonResponse
    {
        return new JsonResponse($this->microMapper->map($this->settings->settings(), PlatformSettingsApi::class));
    }

    #[OA\Patch(
        summary: 'Changes the platform settings.',
        security: [['Bearer' => []]],
    )]
    #[OA\Response(
        response: 200,
        description: 'The platform settings after the change.',
        content: new Model(type: PlatformSettingsApi::class)
    )]
    #[OA\Response(
        response: 409,
        description: 'The settings still come from the environment, until the installer has generated APP_ENCRYPTION_KEY.',
    )]
    #[OA\Tag(name: 'Platform')]
    #[Route('/v1/platform/settings', name: 'app_platform_settings_patch', methods: [Request::METHOD_PATCH])]
    public function patch(
        #[MapRequestPayload]
        PlatformSettingsUpdate $input,
    ): JsonResponse {
        if ($this->settings->isLegacyMode()) {
            throw new ConflictHttpException('The settings come from the environment until the upgrade is finished.');
        }

        $settings = $this->settings->settings();

        if (null !== $input->reportRetentionDays) {
            $settings->setReportRetentionDays($input->reportRetentionDays);
        }

        if (null !== $input->ipLookupEnabled) {
            $settings->setIpLookupEnabled($input->ipLookupEnabled);
        }

        if (null !== $input->smtpHost) {
            $host = strtolower(trim($input->smtpHost));
            '' === $host ? $settings->clearSmtp() : $settings->setSmtpHost($host);
        }

        // The rest of the mail server only means something with a host.
        if (null !== $settings->getSmtpHost()) {
            if (null !== $input->smtpSecurity) {
                $settings->setSmtpSecurity(SmtpSecurity::from($input->smtpSecurity));
            }

            if (null !== $input->smtpPort) {
                $settings->setSmtpPort($input->smtpPort);
            }

            if (null !== $input->smtpUsername) {
                $username = trim($input->smtpUsername);
                $settings->setSmtpUsername('' === $username ? null : $username);
            }

            if (null !== $input->smtpPassword) {
                $settings->setEncryptedSmtpPassword(
                    '' === $input->smtpPassword ? null : $this->settings->encryptSmtpPassword($input->smtpPassword),
                );
            }
        }

        if (null !== $input->senderAddress) {
            $sender = trim($input->senderAddress);
            $settings->setSenderAddress('' === $sender ? null : $sender);
        }

        if (null !== $input->apiIntakeEnabled) {
            $settings->setApiIntakeEnabled($input->apiIntakeEnabled);
        }
        if (null !== $input->uploadEndpointEnabled) {
            $settings->setUploadEndpointEnabled($input->uploadEndpointEnabled);
        }
        if (null !== $input->webhookEnabled) {
            $settings->setWebhookEnabled($input->webhookEnabled);
        }

        if (null !== $input->reportAddressDomain) {
            $domain = strtolower(trim($input->reportAddressDomain));
            $settings->setReportAddressDomain('' === $domain ? null : $domain);
        }

        $this->repository->save($settings);

        return new JsonResponse($this->microMapper->map($settings, PlatformSettingsApi::class));
    }

    #[OA\Post(
        summary: 'Sends a test email to the account, right away, through the mail server of the settings.',
        security: [['Bearer' => []]],
    )]
    #[OA\Response(
        response: 200,
        description: 'Whether the mail server took the email, and why not when it did not.',
        content: new Model(type: TestEmailResultApi::class)
    )]
    #[OA\Tag(name: 'Platform')]
    #[Route('/v1/platform/mailer/test', name: 'app_platform_mailer_test', methods: [Request::METHOD_POST])]
    public function testEmail(
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        $recipient = (string) $user->getEmail();
        $error = $this->testEmailSender->send($recipient);

        return new JsonResponse(new TestEmailResultApi(
            sent: null === $error,
            recipient: $recipient,
            error: $error,
        ));
    }
}
