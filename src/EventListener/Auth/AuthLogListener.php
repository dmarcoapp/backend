<?php

declare(strict_types=1);

namespace App\EventListener\Auth;

use App\Entity\User\AuthLog;
use App\Entity\User\User;
use App\Enum\Auth\AuthLogAction;
use App\Event\Auth\TwoFactorFailureEvent;
use App\Repository\User\AuthLogRepository;
use App\Service\User\LoginNotificationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Uid\Uuid;

final readonly class AuthLogListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        private AuthLogRepository $authLogRepository,
        private LoginNotificationMailer $loginNotificationMailer,
    ) {}

    #[AsEventListener(event: Events::AUTHENTICATION_SUCCESS)]
    public function onLoginSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }
        $userId = $user->getId();
        if (!$userId instanceof Uuid) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return;
        }

        $ip = $request->getClientIp();
        if (null !== $ip && $this->shouldNotifyNewIp($userId, $ip)) {
            $this->loginNotificationMailer->send(
                $user,
                $ip,
                $request->headers->get('User-Agent'),
                new \DateTimeImmutable(),
            );
        }

        $this->log(
            action: AuthLogAction::LOGIN_SUCCESS,
            userId: $userId,
            request: $request,
        );
    }

    /**
     * A wrong two-factor code leaves the password check successful, so no
     * LoginFailureEvent is dispatched and the attempt would go unrecorded.
     */
    #[AsEventListener]
    public function onTwoFactorFailure(TwoFactorFailureEvent $event): void
    {
        $userId = $event->getUser()->getId();
        if (!$userId instanceof Uuid) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return;
        }

        $this->log(
            action: AuthLogAction::TWO_FACTOR_FAILURE,
            userId: $userId,
            request: $request,
        );
    }

    #[AsEventListener]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $user = $event->getPassport()?->getUser();
        if (!$user instanceof User) {
            return;
        }
        $userId = $user->getId();
        if (!$userId instanceof Uuid) {
            return;
        }

        $this->log(
            action: AuthLogAction::LOGIN_FAILURE,
            userId: $userId,
            request: $event->getRequest(),
        );
    }

    private function log(
        AuthLogAction $action,
        Uuid $userId,
        Request $request,
    ): void {
        $audit = new AuthLog();
        $audit->setAction($action);
        $audit->setUserId($userId);
        $audit->setIp($request->getClientIp());
        $audit->setUserAgent($request->headers->get('User-Agent'));
        $audit->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($audit);
        $this->entityManager->flush();
    }

    /**
     * The address comes from the connection and the configured trusted proxies,
     * unlike the CF-IPCountry header this used to read, which any client could
     * set: forging it raised notifications, and repeating a country the account
     * had already used silenced the real ones. A self-hosted deployment has no
     * Cloudflare in front of it either, so that header was never even present.
     */
    private function shouldNotifyNewIp(Uuid $userId, string $ip): bool
    {
        $successCount = $this->authLogRepository->countLoginSuccesses($userId);
        if (0 === $successCount) {
            return false;
        }

        return !$this->authLogRepository->hasLoginSuccessFromIp($userId, $ip);
    }
}
