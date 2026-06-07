<?php

declare(strict_types=1);

namespace App\Controller\Email;

use App\DTO\Input\Email\Email;
use App\Message\Email\InboundEmail;
use App\Service\Email\InboundReportEmailWebhookSignatureVerifier;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class InboundReportEmailWebhookController extends AbstractController
{
    public function __construct(
        private readonly InboundReportEmailWebhookSignatureVerifier $signatureVerifier,
        private readonly MessageBusInterface $messageBus,
        #[Autowire(env: 'APP_INBOUND_REPORT_EMAIL_WEBHOOK_SECRET')]
        private readonly string $webhookSecret,
    ) {}

    #[OA\Post(
        summary: 'Consumes an inbound DMARC report email.',
    )]
    #[OA\Response(
        response: 200,
        description: 'On success.',
    )]
    #[OA\Tag(name: 'Email')]
    #[Route('/v1/webhook/inbound_report_email', name: 'app_webhook_inbound_report_email', methods: [Request::METHOD_POST])]
    public function consume(
        Request $request,
        #[MapRequestPayload]
        Email $email,
    ): JsonResponse {
        if (!$this->signatureVerifier->verify(
            bodyString: $request->getContent(),
            timestamp: $request->headers->get('x-timestamp')
                ?? throw new BadRequestHttpException('Missing timestamp header.'),
            signatureHeader: $request->headers->get('x-signature')
                ?? throw new BadRequestHttpException('Missing signature header.'),
            secret: $this->webhookSecret,
        )) {
            throw new UnauthorizedHttpException('signature', 'Invalid signature.');
        }

        $this->messageBus->dispatch(new InboundEmail($email));

        return new JsonResponse();
    }
}
