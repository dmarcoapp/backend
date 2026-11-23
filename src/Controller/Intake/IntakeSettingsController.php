<?php

declare(strict_types=1);

namespace App\Controller\Intake;

use App\DTO\Input\Intake\ApiIntakeKeyInput;
use App\DTO\Output\Intake\ApiIntakeKeyApi;
use App\DTO\Output\Intake\IntakeAddressApi;
use App\Entity\Intake\ApiIntakeKey;
use App\Entity\User\User;
use App\Repository\Intake\ApiIntakeKeyRepository;
use App\Repository\Platform\PlatformSettingsRepository;
use App\Service\Intake\ApiIntakeKeys;
use App\Service\Intake\IntakeAddress;
use App\Service\Intake\IntakeAddresses;
use App\Service\Platform\PlatformSettingsProvider;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\ByteString;

/**
 * The API side of report intake: the keys that upload reports, the secret a
 * mail gateway signs with, and the addresses reports can be sent to.
 */
#[IsGranted(User::ROLE)]
final class IntakeSettingsController extends AbstractController
{
    private const int WEBHOOK_SECRET_LENGTH = 48;

    public function __construct(
        private readonly PlatformSettingsProvider $settings,
        private readonly PlatformSettingsRepository $settingsRepository,
        private readonly ApiIntakeKeys $keys,
        private readonly ApiIntakeKeyRepository $keyRepository,
        private readonly IntakeAddresses $addresses,
    ) {}

    #[OA\Get(summary: 'Lists the API keys. Their secrets are not kept, so they are not shown.', security: [['Bearer' => []]])]
    #[OA\Response(response: 200, description: 'The keys.', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: ApiIntakeKeyApi::class))))]
    #[OA\Tag(name: 'Intake')]
    #[Route('/v1/platform/intake/keys', name: 'app_intake_key_list', methods: [Request::METHOD_GET])]
    public function listKeys(): JsonResponse
    {
        $this->assertPlatformMode();

        return new JsonResponse(array_map(
            static fn (ApiIntakeKey $key): ApiIntakeKeyApi => self::keyApi($key),
            $this->keyRepository->findBy([], ['createdAt' => 'ASC']),
        ));
    }

    #[OA\Post(summary: 'Makes an API key. The answer holds the key, which is shown this once.', security: [['Bearer' => []]])]
    #[OA\Response(response: 201, description: 'The key, with its token.', content: new Model(type: ApiIntakeKeyApi::class))]
    #[OA\Tag(name: 'Intake')]
    #[Route('/v1/platform/intake/keys', name: 'app_intake_key_create', methods: [Request::METHOD_POST])]
    public function createKey(
        #[MapRequestPayload]
        ApiIntakeKeyInput $input,
    ): JsonResponse {
        $this->assertPlatformMode();
        [$key, $token] = $this->keys->create(trim((string) $input->name));

        return new JsonResponse(self::keyApi($key, $token), Response::HTTP_CREATED);
    }

    #[OA\Delete(summary: 'Revokes an API key. Uploads with it are refused from then on.', security: [['Bearer' => []]])]
    #[OA\Response(response: 204, description: 'Revoked.')]
    #[OA\Tag(name: 'Intake')]
    #[Route('/v1/platform/intake/keys/{key}', name: 'app_intake_key_delete', methods: [Request::METHOD_DELETE])]
    public function deleteKey(ApiIntakeKey $key): Response
    {
        $this->assertPlatformMode();
        $this->keyRepository->delete($key);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[OA\Post(summary: 'Makes a new secret for the mail gateway to sign its webhooks with. The answer holds it, shown this once. The old one stops working.', security: [['Bearer' => []]])]
    #[OA\Response(response: 201, description: 'The secret.')]
    #[OA\Tag(name: 'Intake')]
    #[Route('/v1/platform/intake/webhook-secret', name: 'app_intake_webhook_secret_create', methods: [Request::METHOD_POST])]
    public function createWebhookSecret(): JsonResponse
    {
        $this->assertPlatformMode();

        $secret = ByteString::fromRandom(self::WEBHOOK_SECRET_LENGTH)->toString();
        $settings = $this->settings->settings();
        $settings->setEncryptedWebhookSecret($this->settings->encryptWebhookSecret($secret));
        $this->settingsRepository->save($settings);

        return new JsonResponse(['secret' => $secret], Response::HTTP_CREATED);
    }

    #[OA\Delete(summary: 'Removes the gateway secret, which turns the gateway webhook off.', security: [['Bearer' => []]])]
    #[OA\Response(response: 204, description: 'Removed.')]
    #[OA\Tag(name: 'Intake')]
    #[Route('/v1/platform/intake/webhook-secret', name: 'app_intake_webhook_secret_delete', methods: [Request::METHOD_DELETE])]
    public function deleteWebhookSecret(): Response
    {
        $this->assertPlatformMode();

        $settings = $this->settings->settings();
        $settings->setEncryptedWebhookSecret(null);
        $this->settingsRepository->save($settings);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[OA\Get(summary: 'The addresses DMARC records can send reports to.', security: [['Bearer' => []]])]
    #[OA\Response(response: 200, description: 'The addresses.', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: IntakeAddressApi::class))))]
    #[OA\Tag(name: 'Intake')]
    #[Route('/v1/platform/intake/addresses', name: 'app_intake_addresses', methods: [Request::METHOD_GET])]
    public function addresses(): JsonResponse
    {
        return new JsonResponse(array_map(
            static fn (IntakeAddress $address): IntakeAddressApi => new IntakeAddressApi($address->address, $address->kind, $address->label),
            $this->addresses->all(),
        ));
    }

    private static function keyApi(ApiIntakeKey $key, ?string $token = null): ApiIntakeKeyApi
    {
        return new ApiIntakeKeyApi(
            (string) $key->getId()?->toRfc4122(),
            $key->getName(),
            ApiIntakeKeys::preview($key),
            $key->getCreatedAt(),
            $key->getLastUsedAt(),
            $token,
        );
    }

    private function assertPlatformMode(): void
    {
        if ($this->settings->isLegacyMode()) {
            throw new ConflictHttpException('API intake settings need APP_ENCRYPTION_KEY, which the installer generates.');
        }
    }
}
