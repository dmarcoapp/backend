<?php

declare(strict_types=1);

namespace App\Controller\Intake;

use App\Enum\Email\ReportSource;
use App\Service\DMARC\ReportXMLProcessor;
use App\Service\Intake\ApiIntakeKeys;
use App\Service\Intake\IncomingReport;
use App\Service\Intake\IngestOutcome;
use App\Service\Intake\ReportArchiveException;
use App\Service\Intake\ReportArchiveExtractor;
use App\Service\Intake\ReportIngestor;
use App\Service\Platform\PlatformSettingsProvider;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Takes a report file uploaded with an API key: the XML itself, or the gzip
 * or zip it was mailed in. This sits outside the signed-in API, in a firewall
 * of its own, so the key is all that lets a request in.
 */
final class ReportUploadController extends AbstractController
{
    public function __construct(
        private readonly PlatformSettingsProvider $settings,
        private readonly ApiIntakeKeys $keys,
        private readonly ReportArchiveExtractor $extractor,
        private readonly ReportXMLProcessor $xmlProcessor,
        private readonly ReportIngestor $ingestor,
        private readonly RateLimiterFactoryInterface $reportIntakeLimiter,
    ) {}

    #[OA\Post(
        summary: 'Uploads a DMARC aggregate report: XML, or the gzip or zip it came in. Authorization: Bearer <API key>.',
    )]
    #[OA\Response(response: 202, description: 'Taken, to be processed.')]
    #[OA\Response(response: 200, description: 'Already received, or from a blocked sender. Nothing more happens.')]
    #[OA\Response(response: 401, description: 'No valid API key.')]
    #[OA\Response(response: 404, description: 'Report intake through the API is off.')]
    #[OA\Response(response: 413, description: 'The file is larger than 10 MB.')]
    #[OA\Response(response: 422, description: 'The file is not a report DMARCo accepts. The body says why.')]
    #[OA\Response(response: 429, description: 'Too many requests from this address.')]
    #[OA\Tag(name: 'Intake')]
    #[Route('/v1/intake/reports', name: 'app_intake_report_upload', methods: [Request::METHOD_POST])]
    public function upload(Request $request): JsonResponse
    {
        if ($this->settings->isLegacyMode() || !$this->settings->isApiIntakeEnabled()) {
            throw new NotFoundHttpException('Report intake through the API is off.');
        }

        // Counted before the key is checked, so guessing keys is throttled too.
        $limit = $this->reportIntakeLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - time()));
        }

        $key = $this->keys->authenticate($request->headers->get('Authorization'));
        if (null === $key) {
            return new JsonResponse(['error' => 'A valid API key is required.'], Response::HTTP_UNAUTHORIZED, ['WWW-Authenticate' => 'Bearer']);
        }

        if ((int) $request->headers->get('Content-Length', '0') > ReportArchiveExtractor::MAX_INPUT_BYTES) {
            return new JsonResponse(['error' => 'The file is larger than the size limit.'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $content = $request->getContent();
        if (strlen($content) > ReportArchiveExtractor::MAX_INPUT_BYTES) {
            return new JsonResponse(['error' => 'The file is larger than the size limit.'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        try {
            $xml = $this->extractor->extract($content);
        } catch (ReportArchiveException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Checked now as well as later, so the uploader learns it right away.
        if (!$this->xmlProcessor->validate($xml)) {
            return new JsonResponse(['error' => 'The file is not a valid DMARC aggregate report.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $outcome = $this->ingestor->ingest(new IncomingReport(
            source: ReportSource::API,
            // The same file uploaded twice is recognized by its content.
            messageId: sprintf('<%s@api.dmarco>', hash('sha256', $xml)),
            fromAddress: self::emailHeader($request, 'X-Report-From') ?? 'api-key:'.$key->getName(),
            toAddress: self::emailHeader($request, 'X-Report-Recipient') ?? 'api',
            receivedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            filename: (string) $request->headers->get('X-Report-Filename', 'report.xml'),
            xml: $xml,
            apiKey: $key,
        ));

        return new JsonResponse(
            ['status' => $outcome->value],
            IngestOutcome::ACCEPTED === $outcome ? Response::HTTP_ACCEPTED : Response::HTTP_OK,
        );
    }

    private static function emailHeader(Request $request, string $name): ?string
    {
        $value = trim((string) $request->headers->get($name, ''));

        return false === filter_var($value, FILTER_VALIDATE_EMAIL) ? null : $value;
    }
}
