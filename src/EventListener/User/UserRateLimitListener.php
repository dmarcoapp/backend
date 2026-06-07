<?php

declare(strict_types=1);

namespace App\EventListener\User;

use App\Entity\User\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Uid\Uuid;

#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest')]
final readonly class UserRateLimitListener
{
    public function __construct(
        private RateLimiterFactoryInterface $loggedInInteractionLimiter,
        private TokenStorageInterface $tokenStorage,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (Request::METHOD_OPTIONS === $request->getMethod()) {
            return;
        }

        if (!str_starts_with($request->getPathInfo(), '/v1')) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        $userId = $user->getId();
        if (!$userId instanceof Uuid) {
            return;
        }

        $rateLimit = $this->loggedInInteractionLimiter
            ->create($userId->toRfc4122())
            ->consume(1)
        ;

        if (false === $rateLimit->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }
    }
}
