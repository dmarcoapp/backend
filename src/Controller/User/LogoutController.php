<?php

declare(strict_types=1);

namespace App\Controller\User;

use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\MissingClaimException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\BlockedTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
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
        private readonly BlockedTokenManagerInterface $blockedTokenManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly TokenExtractorInterface $tokenExtractor,
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

        $this->blockCurrentAccessToken($request);

        $event = $this->eventDispatcher->dispatch(new LogoutEvent($request, $token));

        return $event->getResponse() ?? new JsonResponse();
    }

    private function blockCurrentAccessToken(Request $request): void
    {
        $rawToken = $this->tokenExtractor->extract($request);
        if (false === $rawToken) {
            return;
        }

        try {
            $this->blockedTokenManager->add($this->jwtTokenManager->parse($rawToken));
        } catch (JWTDecodeFailureException|MissingClaimException) {
            return;
        }
    }
}
