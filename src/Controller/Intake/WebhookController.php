<?php

declare(strict_types=1);

namespace App\Controller\Intake;

use App\DTO\Input\Email\Email;
use App\Message\Email\InboundEmail;
use App\Service\Email\AggregateReportPostboxResolver;
use App\Service\Email\InboundReportEmailWebhookSignatureVerifier;
use App\Service\Email\ReportAttachmentDecoder;
use App\Service\Platform\PlatformSettingsProvider;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class WebhookController extends AbstractController
{
    public function __construct(
        private readonly InboundReportEmailWebhookSignatureVerifier $signatureVerifier,
        private readonly AggregateReportPostboxResolver $postboxResolver,
        private readonly ReportAttachmentDecoder $attachmentDecoder,
        private readonly MessageBusInterface $messageBus,
        private readonly PlatformSettingsProvider $settings,
    ) {}

    #[OA\Post(
        summary: 'Consumes an inbound DMARC report email.',
    )]
    #[OA\Response(
        response: 200,
        description: 'On success.',
    )]
    #[OA\Response(
        response: 404,
        description: 'The recipient is not a known report postbox, so the sender is expected to discard the message.',
    )]
    #[OA\Response(
        response: 503,
        description: 'The API or the webhook is off, or no webhook secret is set. The sender is expected to keep the message and try again.',
    )]
    #[OA\Response(
        response: 422,
        description: 'The attached report cannot be unpacked. The sender is expected to keep the message.',
    )]
    #[OA\Tag(name: 'Intake')]
    #[Route('/v1/intake/webhook', name: 'app_intake_webhook', methods: [Request::METHOD_POST])]
    // Where gateways set up before 0.5 post. Kept so they need no change.
    #[Route('/v1/webhook/inbound_report_email', name: 'app_intake_webhook_legacy', methods: [Request::METHOD_POST])]
    public function consume(
        Request $request,
        #[MapRequestPayload]
        Email $email,
    ): JsonResponse {
        // Without API intake, or without a secret to check against, nothing is
        // taken. Not with a 404, which tells the gateway to discard the message:
        // switching intake off for a while must not cost the reports sent
        // meanwhile.
        if (!$this->settings->isWebhookEnabled()) {
            throw new ServiceUnavailableHttpException(message: 'The webhook is off.');
        }
        $secret = $this->settings->webhookSecret();
        if (null === $secret || '' === $secret) {
            throw new ServiceUnavailableHttpException(message: 'The webhook has no secret yet.');
        }

        if (!$this->signatureVerifier->verify(
            bodyString: $request->getContent(),
            timestamp: $request->headers->get('x-timestamp')
                ?? throw new BadRequestHttpException('Missing timestamp header.'),
            signatureHeader: $request->headers->get('x-signature')
                ?? throw new BadRequestHttpException('Missing signature header.'),
            secret: $secret,
        )) {
            throw new UnauthorizedHttpException('signature', 'Invalid signature.');
        }

        // Anyone can mail an address at the report domain. Refusing an unknown
        // recipient here, rather than dropping it later, tells the sender to
        // discard the message instead of keeping it for another attempt.
        $recipient = $email->to[0] ?? '';
        if (!is_string($recipient) || !$this->postboxResolver->isReportAddress($recipient)) {
            throw new NotFoundHttpException('Unknown report recipient.');
        }

        // Unpacked here as well as in the handler, so that a report that cannot
        // be read is refused while the gateway still holds it, rather than lost
        // after it has been accepted.
        $content = $email->attachments[0]['content'] ?? null;

        try {
            $this->attachmentDecoder->decode(is_string($content) ? $content : '');
        } catch (\InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        $this->messageBus->dispatch(new InboundEmail($email));

        return new JsonResponse();
    }
}
