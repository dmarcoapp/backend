<?php

declare(strict_types=1);

namespace App\Controller\User;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class LogoutController extends AbstractController
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    #[OA\Post(
        summary: 'Logs out the current user.',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'refresh_token',
                    type: 'string',
                ),
            ],
            type: 'object'
        )),
        tags: ['User']
    )]
    #[Route('/v1/user/logout', name: 'app_user_logout', methods: [Request::METHOD_POST])]
    public function logout(
        Request $request,
    ): JsonResponse {
        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            throw $this->createAccessDeniedException();
        }

        $this->eventDispatcher->dispatch(new LogoutEvent($request, $token));

        return new JsonResponse();
    }
}
