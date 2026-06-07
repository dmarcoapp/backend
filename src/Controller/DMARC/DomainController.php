<?php

declare(strict_types=1);

namespace App\Controller\DMARC;

use App\DTO\Input\Common\Pagination;
use App\DTO\Output\Common\PaginationApi;
use App\DTO\Output\Common\PaginationMetaApi;
use App\DTO\Output\DMARC\DomainApi;
use App\Entity\DMARC\Domain;
use App\Entity\User\User;
use App\Repository\DMARC\DomainRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfonycasts\MicroMapper\MicroMapperInterface;

#[IsGranted('ROLE_USER')]
final class DomainController extends AbstractController
{
    public function __construct(
        private readonly MicroMapperInterface $microMapper,
        private readonly DomainRepository $repository,
    ) {}

    #[OA\Get(
        description: 'The domain list is an aggregation of the user\'s dmarc reports.',
        summary: 'Gets a list of domains for the authenticated user.',
        security: [['Bearer' => []]],
    )]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of domains.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(
                        ref: new Model(type: DomainApi::class)
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
    #[Route('/v1/dmarc/domains', name: 'app_domains_list', methods: [Request::METHOD_GET])]
    public function list(
        #[MapQueryString]
        Pagination $pagination,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        $paginator = $this->repository->getDomainsForUserPaginated($user, $pagination);

        return new JsonResponse(
            new PaginationApi(
                items: $this->microMapper->mapMultiple($paginator, DomainApi::class),
                meta: new PaginationMetaApi($paginator, Domain::class)
            )
        );
    }

    #[OA\Get(
        summary: 'Gets a single domain.',
        security: [['Bearer' => []]],
    )]
    #[OA\Response(
        response: 200,
        description: 'A single domain.',
        content: new Model(type: DomainApi::class)
    )]
    #[OA\Tag(name: 'DMARC')]
    #[Route('/v1/dmarc/domains/{domain}', name: 'app_domains_get', methods: [Request::METHOD_GET])]
    public function get(
        Domain $domain,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        $domainUserId = $domain->getUser()->getId();
        $userId = $user->getId();

        if (!$domainUserId || !$userId || !$domainUserId->equals($userId)) {
            throw $this->createAccessDeniedException();
        }

        return new JsonResponse(
            $this->microMapper->map($domain, DomainApi::class)
        );
    }
}
