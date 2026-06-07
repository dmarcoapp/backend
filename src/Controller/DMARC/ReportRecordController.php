<?php

declare(strict_types=1);

namespace App\Controller\DMARC;

use App\DTO\Input\Common\Pagination;
use App\DTO\Output\Common\PaginationApi;
use App\DTO\Output\Common\PaginationMetaApi;
use App\DTO\Output\DMARC\ReportRecordApi;
use App\Entity\DMARC\Report;
use App\Entity\DMARC\ReportRecord;
use App\Entity\User\User;
use App\Repository\DMARC\ReportRecordRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Symfonycasts\MicroMapper\MicroMapperInterface;

#[IsGranted('ROLE_USER')]
final class ReportRecordController extends AbstractController
{
    public function __construct(
        private readonly MicroMapperInterface $microMapper,
        private readonly ReportRecordRepository $repository,
    ) {}

    #[OA\Get(
        summary: 'Gets a list of records for a given report.',
        security: [['Bearer' => []]],
    )]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of records.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(
                        ref: new Model(type: ReportRecordApi::class)
                    )
                ),
                new OA\Property(
                    property: 'meta',
                    ref: new Model(type: PaginationMetaApi::class)
                ),
            ],
            type: 'object'
        )
    )]
    #[OA\Tag(name: 'DMARC')]
    #[Route('/v1/dmarc/reports/{report}/records', name: 'app_records_list_by_report', methods: [Request::METHOD_GET])]
    public function listByReport(
        Report $report,
        #[MapQueryString]
        Pagination $pagination,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        $reportEmail = $report->getEmail();
        $reportOwner = $reportEmail?->getOwner();
        $reportOwnerId = $reportOwner?->getId();
        $userId = $user->getId();

        if (
            !$reportOwnerId instanceof Uuid
            || !$userId instanceof Uuid
            || !$reportOwnerId->equals($userId)
        ) {
            throw $this->createAccessDeniedException();
        }

        $paginator = $this->repository->getReportRecordsForReportPaginated($report, $pagination);

        return new JsonResponse(
            new PaginationApi(
                items: $this->microMapper->mapMultiple($paginator, ReportRecordApi::class),
                meta: new PaginationMetaApi($paginator, ReportRecord::class)
            )
        );
    }
}
