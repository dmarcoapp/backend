<?php

declare(strict_types=1);

namespace App\EventListener\Auth;

use App\Entity\User\AuthLog;
use App\Entity\User\User;
use App\Enum\Auth\AuthLogAction;
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

        $countryCode = $this->resolveCountryCode($request);
        if (null !== $countryCode && $this->shouldNotifyNewCountry($userId, $countryCode)) {
            $this->loginNotificationMailer->send(
                $user,
                $countryCode,
                $request->getClientIp(),
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
        $audit->setCountryCode($this->resolveCountryCode($request));
        $audit->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($audit);
        $this->entityManager->flush();
    }

    private function resolveCountryCode(Request $request): ?string
    {
        $countryCode = $request->headers->get('CF-IPCountry');
        if (null === $countryCode) {
            return null;
        }

        $countryCode = strtoupper(trim($countryCode));
        if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
            return null;
        }

        return $countryCode;
    }

    private function shouldNotifyNewCountry(Uuid $userId, string $countryCode): bool
    {
        $successCount = $this->authLogRepository->countLoginSuccesses($userId);
        if (0 === $successCount) {
            return false;
        }

        return !$this->authLogRepository->hasLoginSuccessFromCountry($userId, $countryCode);
    }
}
