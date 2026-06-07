<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\DTO\Input\Common\Pagination;
use App\DTO\Input\User\BlocklistEntryBulkDelete;
use App\DTO\Input\User\BlocklistEntryCreate;
use App\DTO\Output\Common\PaginationApi;
use App\DTO\Output\Common\PaginationMetaApi;
use App\DTO\Output\User\BlocklistEntryApi;
use App\Entity\User\BlocklistEntry;
use App\Entity\User\User;
use App\Repository\User\BlocklistEntryRepository;
use App\Service\User\BlocklistMatcher;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Symfonycasts\MicroMapper\MicroMapperInterface;

#[IsGranted('ROLE_USER')]
final class BlocklistController extends AbstractController
{
    public function __construct(
        private readonly MicroMapperInterface $microMapper,
        private readonly BlocklistEntryRepository $repository,
    ) {}

    #[OA\Get(
        summary: 'Gets a paginated blocklist for the authenticated user.',
        security: [['Bearer' => []]],
    )]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of blocklist entries.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(
                        ref: new Model(type: BlocklistEntryApi::class)
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
    #[OA\Tag(name: 'User')]
    #[Route('/v1/user/blocklist', name: 'app_blocklist_list', methods: [Request::METHOD_GET])]
    public function list(
        #[MapQueryString]
        Pagination $pagination,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        $paginator = $this->repository->getBlocklistEntriesForUserPaginated($user, $pagination);

        return new JsonResponse(
            new PaginationApi(
                items: $this->microMapper->mapMultiple($paginator, BlocklistEntryApi::class),
                meta: new PaginationMetaApi($paginator, BlocklistEntry::class),
            )
        );
    }

    #[OA\Post(
        summary: 'Creates a blocklist entry for the authenticated user.',
        security: [['Bearer' => []]],
    )]
    #[OA\Response(
        response: 201,
        description: 'Created blocklist entry.',
        content: new Model(type: BlocklistEntryApi::class)
    )]
    #[OA\Tag(name: 'User')]
    #[Route('/v1/user/blocklist', name: 'app_blocklist_create', methods: [Request::METHOD_POST])]
    public function create(
        #[MapRequestPayload]
        BlocklistEntryCreate $input,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        $pattern = BlocklistMatcher::normalizeValue($input->pattern ?? '');
        if ($this->repository->findOneBy(['user' => $user, 'pattern' => $pattern])) {
            throw new ConflictHttpException('Blocklist entry already exists.');
        }

        $entry = new BlocklistEntry($user, $pattern);
        $this->repository->save($entry);

        return new JsonResponse(
            $this->microMapper->map($entry, BlocklistEntryApi::class),
            Response::HTTP_CREATED,
        );
    }

    #[OA\Delete(
        summary: 'Deletes a blocklist entry for the authenticated user.',
        security: [['Bearer' => []]],
    )]
    #[OA\Response(
        response: 200,
        description: 'Blocklist entry deleted.',
    )]
    #[OA\Tag(name: 'User')]
    #[Route('/v1/user/blocklist/{blocklistEntry}', name: 'app_blocklist_delete', methods: [Request::METHOD_DELETE])]
    public function delete(
        BlocklistEntry $blocklistEntry,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        $this->assertEntryOwnership($blocklistEntry, $user);

        $this->repository->delete($blocklistEntry);

        return new JsonResponse();
    }

    #[OA\Delete(
        summary: 'Deletes selected blocklist entries for the authenticated user.',
        security: [['Bearer' => []]],
    )]
    #[OA\Response(
        response: 200,
        description: 'Bulk delete result.',
    )]
    #[OA\Tag(name: 'User')]
    #[Route('/v1/user/blocklist', name: 'app_blocklist_delete_bulk', methods: [Request::METHOD_DELETE])]
    public function deleteBulk(
        #[MapRequestPayload]
        BlocklistEntryBulkDelete $input,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        $ids = array_values(array_unique($input->ids));
        $entries = $this->repository->getEntriesByIdsForUser($user, $ids);

        if (count($ids) !== count($entries)) {
            throw $this->createAccessDeniedException();
        }

        $this->repository->deleteMany($entries);

        return new JsonResponse([
            'deletedCount' => count($entries),
        ]);
    }

    private function assertEntryOwnership(BlocklistEntry $blocklistEntry, User $user): void
    {
        $entryUserId = $blocklistEntry->getUser()->getId();
        $userId = $user->getId();

        if (
            !$entryUserId instanceof Uuid
            || !$userId instanceof Uuid
            || !$entryUserId->equals($userId)
        ) {
            throw $this->createAccessDeniedException();
        }
    }
}
