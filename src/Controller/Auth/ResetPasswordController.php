<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\DTO\Input\Auth\PasswordReset;
use App\DTO\Input\Auth\RequestPasswordReset;
use App\Event\User\PasswordChangeEvent;
use App\Event\User\PasswordResetEvent;
use App\Repository\User\UserRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ResetPasswordController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly RateLimiterFactoryInterface $anonymousApiLimiter,
        private readonly RateLimiterFactoryInterface $anonymousSendmailLimiter,
    ) {}

    #[OA\Post(
        summary: 'Requests a password reset email.',
        security: [],
        tags: ['Authentication'],
    )]
    #[Route('/v1/auth/request_password_reset', name: 'app_request_password_reset', methods: [Request::METHOD_POST])]
    public function requestPasswordReset(
        Request $request,
        #[MapRequestPayload]
        RequestPasswordReset $input,
    ): JsonResponse {
        $apiLimiter = $this->anonymousApiLimiter->create($request->getClientIp());
        if (false === $apiLimiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        $sendmailLimiter = $this->anonymousSendmailLimiter->create($input->email);
        if (false === $sendmailLimiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        $user = $this->userRepository->findOneBy(['email' => $input->email]);

        if ($user) {
            $this->eventDispatcher->dispatch(new PasswordResetEvent($user));
        }

        return new JsonResponse([
            'message' => 'Password reset request sent.',
            'code' => 200,
        ]);
    }

    #[OA\Post(
        summary: 'Resets a user password.',
        security: [],
        tags: ['Authentication'],
    )]
    #[Route('/v1/auth/reset_password', name: 'app_reset_password', methods: [Request::METHOD_POST])]
    public function resetPassword(
        Request $request,
        #[MapRequestPayload]
        PasswordReset $input,
    ): JsonResponse {
        $apiLimiter = $this->anonymousApiLimiter->create($request->getClientIp());
        if (false === $apiLimiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        $emailLimiter = $this->anonymousSendmailLimiter->create($input->email);
        if (false === $emailLimiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        $user = $this->userRepository->findOneBy(['passwordResetToken' => $input->token, 'email' => $input->email]);

        if (!$user) {
            throw new NotFoundHttpException('Token not found.');
        }

        if (!$user->getPasswordResetTokenExpiresAt() || $user->getPasswordResetTokenExpiresAt() < new \DateTimeImmutable()) {
            $user->setPasswordResetToken(null);
            $user->setPasswordResetTokenExpiresAt(null);
            $this->userRepository->save($user);

            throw new UnauthorizedHttpException('Token expired.');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $input->password));
        $user->setPasswordResetToken(null);
        $user->setPasswordResetTokenExpiresAt(null);
        $this->userRepository->save($user);
        $this->eventDispatcher->dispatch(new PasswordChangeEvent($user));

        return new JsonResponse([
            'message' => 'Password reset successfully.',
            'code' => 200,
        ]);
    }
}
