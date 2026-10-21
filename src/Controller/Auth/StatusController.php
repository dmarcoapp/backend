<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\DTO\Output\Auth\StatusApi;
use App\Repository\User\UserRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class StatusController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly RateLimiterFactoryInterface $anonymousApiLimiter,
    ) {}

    // Accounts are created on the server, never through the API, so a fresh
    // install has nobody to sign in as. This lets the sign-in page say so, and
    // point to the command, instead of rejecting every attempt.
    #[OA\Response(
        response: 200,
        description: 'Whether the instance has an account to sign in with.',
        content: new Model(type: StatusApi::class)
    )]
    #[OA\Get(
        summary: 'Gets what the sign-in page needs to know before anyone signs in.',
        security: [],
        tags: ['Authentication']
    )]
    #[Route('/v1/auth/status', name: 'app_auth_status', methods: [Request::METHOD_GET])]
    public function get(Request $request): JsonResponse
    {
        if (!$this->anonymousApiLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        $status = new StatusApi();
        $status->accountExists = $this->userRepository->hasAny();

        return new JsonResponse($status);
    }
}
