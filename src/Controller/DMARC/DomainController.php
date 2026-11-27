<?php

declare(strict_types=1);

namespace App\Controller\DMARC;

use App\DTO\Input\Common\Pagination;
use App\DTO\Output\Common\PaginationApi;
use App\DTO\Output\Common\PaginationMetaApi;
use App\DTO\Output\DMARC\DomainApi;
use App\Entity\DMARC\Domain;
use App\Message\DMARC\ProcessDomain;
use App\MessageHandler\DMARC\ProcessDomainHandler;
use App\Repository\DMARC\DomainRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfonycasts\MicroMapper\MicroMapperInterface;

#[IsGranted('ROLE_USER')]
final class DomainController extends AbstractController
{
    // A check on demand waits this long after the domain's last check,
    // whether that one came from the schedule or from a click.
    public const int CHECK_COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly MicroMapperInterface $microMapper,
        private readonly DomainRepository $repository,
        private readonly ProcessDomainHandler $processDomain,
    ) {}

    #[OA\Get(
        description: 'The domain list is an aggregation of the dmarc reports.',
        summary: 'Gets the list of domains.',
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
    ): JsonResponse {
        $paginator = $this->repository->getDomainsPaginated($pagination);

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
    ): JsonResponse {
        return new JsonResponse(
            $this->microMapper->map($domain, DomainApi::class)
        );
    }

    #[OA\Post(
        description: 'Reads the domain\'s DMARC record from DNS now, instead of at its next scheduled check. Refused for a minute after the last check.',
        summary: 'Checks a domain again.',
        security: [['Bearer' => []]],
    )]
    #[OA\Response(
        response: 200,
        description: 'The domain after the check.',
        content: new Model(type: DomainApi::class)
    )]
    #[OA\Response(response: 429, description: 'The domain was checked less than a minute ago; Retry-After says when to try again.')]
    #[OA\Tag(name: 'DMARC')]
    #[Route('/v1/dmarc/domains/{domain}/check', name: 'app_domains_check', methods: [Request::METHOD_POST])]
    public function check(
        Domain $domain,
    ): JsonResponse {
        $lastChecked = $domain->getLastChecked();
        if (null !== $lastChecked) {
            $wait = $lastChecked->getTimestamp() + self::CHECK_COOLDOWN_SECONDS - time();
            if ($wait > 0) {
                throw new TooManyRequestsHttpException($wait);
            }
        }

        ($this->processDomain)(new ProcessDomain((string) $domain->getId()));

        return new JsonResponse(
            $this->microMapper->map($domain, DomainApi::class)
        );
    }
}
