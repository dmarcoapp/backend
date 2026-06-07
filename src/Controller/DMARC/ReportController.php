<?php

declare(strict_types=1);

namespace App\Controller\DMARC;

use App\DTO\Input\Common\Pagination;
use App\DTO\Input\DMARC\ReportBulkDelete;
use App\DTO\Output\Common\PaginationApi;
use App\DTO\Output\Common\PaginationMetaApi;
use App\DTO\Output\DMARC\ReportApi;
use App\Entity\DMARC\Report;
use App\Entity\User\User;
use App\Repository\DMARC\ReportRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Symfonycasts\MicroMapper\MicroMapperInterface;

#[IsGranted('ROLE_USER')]
final class ReportController extends AbstractController
{
    public function __construct(
        private readonly MicroMapperInterface $microMapper,
        private readonly ReportRepository $repository,
    ) {}

    #[OA\Get(
        summary: 'Gets a list of dmarc reports for the authenticated user.',
        security: [['Bearer' => []]],
    )]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of reports.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(
                        ref: new Model(type: ReportApi::class)
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
    #[Route('/v1/dmarc/reports', name: 'app_reports_list', methods: [Request::METHOD_GET])]
    public function list(
        #[MapQueryString]
        Pagination $pagination,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        $paginator = $this->repository->getReportsForUserPaginated($user, $pagination);

        return new JsonResponse(
            new PaginationApi(
                items: $this->microMapper->mapMultiple($paginator, ReportApi::class),
                meta: new PaginationMetaApi($paginator, Report::class)
            )
        );
    }

    #[OA\Get(
        summary: 'Gets a single dmarc report.',
        security: [['Bearer' => []]],
    )]
    #[OA\Response(
        response: 200,
        description: 'A single dmarc report.',
        content: new Model(type: ReportApi::class)
    )]
    #[OA\Tag(name: 'DMARC')]
    #[Route('/v1/dmarc/reports/{report}', name: 'app_reports_get', methods: [Request::METHOD_GET])]
    public function get(
        Report $report,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        $this->assertReportOwnership($report, $user);

        return new JsonResponse(
            $this->microMapper->map($report, ReportApi::class)
        );
    }

    #[OA\Get(
        summary: 'Gets a single dmarc report\'s raw report xml data.',
        security: [['Bearer' => []]],
    )]
    #[OA\Response(
        response: 200,
        description: 'A single dmarc report\'s raw report xml data.',
    )]
    #[OA\Tag(name: 'DMARC')]
    #[Route('/v1/dmarc/reports/{report}/xml', name: 'app_reports_get_xml', methods: [Request::METHOD_GET])]
    public function xml(
        Report $report,
        #[CurrentUser]
        User $user,
    ): Response {
        $this->assertReportOwnership($report, $user);

        return new Response($report->getRawXML(), 200, ['Content-Type' => 'application/xml']);
    }

    #[OA\Delete(
        summary: 'Deletes a single dmarc report for the authenticated user.',
        security: [['Bearer' => []]],
    )]
    #[OA\Response(
        response: 200,
        description: 'Report deleted.',
    )]
    #[OA\Tag(name: 'DMARC')]
    #[Route('/v1/dmarc/reports/{report}', name: 'app_reports_delete', methods: [Request::METHOD_DELETE])]
    public function delete(
        Report $report,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        $this->assertReportOwnership($report, $user);

        $this->repository->delete($report);

        return new JsonResponse();
    }

    #[OA\Delete(
        summary: 'Deletes selected dmarc reports for the authenticated user.',
        security: [['Bearer' => []]],
    )]
    #[OA\Response(
        response: 200,
        description: 'Bulk delete result.',
    )]
    #[OA\Tag(name: 'DMARC')]
    #[Route('/v1/dmarc/reports', name: 'app_reports_delete_bulk', methods: [Request::METHOD_DELETE])]
    public function deleteBulk(
        #[MapRequestPayload]
        ReportBulkDelete $input,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        $ids = array_values(array_unique($input->ids));
        $reports = $this->repository->getReportsByIdsForUser($user, $ids);

        if (count($ids) !== count($reports)) {
            throw $this->createAccessDeniedException();
        }

        $this->repository->deleteMany($reports);

        return new JsonResponse([
            'deletedCount' => count($reports),
        ]);
    }

    private function assertReportOwnership(Report $report, User $user): void
    {
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
    }
}
