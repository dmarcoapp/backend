<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\DTO\Input\Auth\EmailVerify;
use App\DTO\Input\Auth\Registration;
use App\DTO\Input\Auth\RequestVerificationResend;
use App\Event\User\EmailVerificationResendEvent;
use App\Repository\User\UserRepository;
use App\Service\User\UserFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserFactory $userFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RateLimiterFactoryInterface $anonymousApiLimiter,
        private readonly RateLimiterFactoryInterface $anonymousSendmailLimiter,
        #[Autowire(env: 'bool:APP_REGISTRATION_ENABLED')]
        private readonly bool $registrationEnabled
    ) {}

    #[OA\Post(
        summary: 'Registers a new user.',
        security: [],
        tags: ['Authentication']
    )]
    #[Route('/v1/auth/register', name: 'app_register', methods: [Request::METHOD_POST])]
    public function register(
        Request $request,
        #[MapRequestPayload]
        Registration $input,
    ): JsonResponse {
        if (false === $this->registrationEnabled) {
            throw new BadRequestHttpException('Registration is disabled.');
        }

        $apiLimiter = $this->anonymousApiLimiter->create($request->getClientIp());
        if (false === $apiLimiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        // TODO: recaptcha

        $user = $this->userRepository->findOneBy(['email' => $input->email]);
        if ($user) {
            throw new BadRequestHttpException('User already exists.');
        }

        $this->userFactory->create(
            email: $input->email,
            name: $input->name,
            plainPassword: $input->password,
            roles: ['ROLE_USER'],
        );

        return new JsonResponse([
            'message' => 'User created successfully.',
            'code' => 200,
        ]);
    }

    #[OA\Post(
        summary: 'Requests a verification email.',
        security: [],
        tags: ['Authentication'],
    )]
    #[Route('/v1/auth/request_verification_resend', name: 'app_request_verification_resend', methods: [Request::METHOD_POST])]
    public function requestVerificationResend(
        Request $request,
        #[MapRequestPayload]
        RequestVerificationResend $input,
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
            $this->eventDispatcher->dispatch(new EmailVerificationResendEvent($user));
        }

        return new JsonResponse([
            'message' => 'Email verification email resent.',
            'code' => 200,
        ]);
    }

    #[OA\Post(
        summary: 'Verifies a user email address.',
        security: [],
        tags: ['Authentication']
    )]
    #[Route('/v1/auth/verify', name: 'app_verify', methods: [Request::METHOD_POST])]
    public function verify(
        Request $request,
        #[MapRequestPayload]
        EmailVerify $input,
    ): JsonResponse {
        $limiter = $this->anonymousApiLimiter->create($request->getClientIp());

        if (false === $limiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        $user = $this->userRepository->findOneBy(['emailVerificationToken' => $input->token, 'email' => $input->email]);

        if (!$user) {
            throw new NotFoundHttpException('Token not found.');
        }

        if ($user->isVerified()) {
            throw new BadRequestHttpException('User is already verified.');
        }

        if (!$user->getEmailVerificationTokenExpiresAt() || $user->getEmailVerificationTokenExpiresAt() < new \DateTimeImmutable()) {
            throw new UnauthorizedHttpException('Token expired.');
        }

        $user->setEmailVerificationToken(null);
        $user->setEmailVerificationTokenExpiresAt(null);
        $this->userRepository->save($user);

        return new JsonResponse([
            'message' => 'Email verified successfully.',
            'code' => 200,
        ]);
    }
}
