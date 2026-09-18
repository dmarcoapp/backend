<?php

declare(strict_types=1);

namespace App\Tests\EventListener\Auth;

use App\Entity\User\AuthLog;
use App\Entity\User\User;
use App\Enum\Auth\AuthLogAction;
use App\Event\Auth\TwoFactorFailureEvent;
use App\EventListener\Auth\AuthLogListener;
use App\Repository\User\AuthLogRepository;
use App\Service\User\LoginNotificationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Rule\InvokedCount;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(AuthLogListener::class)]
final class AuthLogListenerTest extends TestCase
{
    public function testLoginSuccessDoesNotNotifyOnFirstLogin(): void
    {
        $request = Request::create('/v1/auth/login_check', 'POST');
        $request->headers->set('CF-IPCountry', 'hu');
        $request->headers->set('User-Agent', 'TestAgent');
        $request->server->set('REMOTE_ADDR', '203.0.113.10');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $authLogRepository = $this->createMock(AuthLogRepository::class);
        $authLogRepository->expects(self::once())->method('countLoginSuccesses')->willReturn(0);
        $authLogRepository->expects(self::never())->method('hasLoginSuccessFromCountry');

        $mailer = $this->createMailer(self::never());

        $listener = new AuthLogListener(
            $entityManager,
            $requestStack,
            $authLogRepository,
            new LoginNotificationMailer($mailer)
        );

        $user = $this->createUserWithId();
        $event = new AuthenticationSuccessEvent([], $user, new Response());

        $listener->onLoginSuccess($event);
    }

    public function testLoginSuccessNotifiesOnNewCountry(): void
    {
        $request = Request::create('/v1/auth/login_check', 'POST');
        $request->headers->set('CF-IPCountry', 'HU');
        $request->headers->set('User-Agent', 'TestAgent');
        $request->server->set('REMOTE_ADDR', '203.0.113.10');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $authLogRepository = $this->createMock(AuthLogRepository::class);
        $authLogRepository->expects(self::once())->method('countLoginSuccesses')->willReturn(2);
        $authLogRepository->expects(self::once())
            ->method('hasLoginSuccessFromCountry')
            ->with(self::isInstanceOf(Uuid::class), 'HU')
            ->willReturn(false)
        ;

        $mailer = $this->createMailer(self::once());

        $listener = new AuthLogListener(
            $entityManager,
            $requestStack,
            $authLogRepository,
            new LoginNotificationMailer($mailer)
        );

        $user = $this->createUserWithId();
        $event = new AuthenticationSuccessEvent([], $user, new Response());

        $listener->onLoginSuccess($event);
    }

    public function testLoginSuccessDoesNotNotifyWhenNotificationDisabled(): void
    {
        $request = Request::create('/v1/auth/login_check', 'POST');
        $request->headers->set('CF-IPCountry', 'HU');
        $request->headers->set('User-Agent', 'TestAgent');
        $request->server->set('REMOTE_ADDR', '203.0.113.10');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $authLogRepository = $this->createMock(AuthLogRepository::class);
        $authLogRepository->expects(self::once())->method('countLoginSuccesses')->willReturn(2);
        $authLogRepository->expects(self::once())
            ->method('hasLoginSuccessFromCountry')
            ->with(self::isInstanceOf(Uuid::class), 'HU')
            ->willReturn(false)
        ;

        $mailer = $this->createMailer(self::never());

        $listener = new AuthLogListener(
            $entityManager,
            $requestStack,
            $authLogRepository,
            new LoginNotificationMailer($mailer)
        );

        $user = $this->createUserWithId();
        $user->setUnusualNewLoginNotificationEnabled(false);

        $event = new AuthenticationSuccessEvent([], $user, new Response());

        $listener->onLoginSuccess($event);
    }

    public function testLoginFailureSkipsWhenPassportHasNoUser(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $listener = new AuthLogListener(
            $entityManager,
            new RequestStack(),
            $this->createStub(AuthLogRepository::class),
            new LoginNotificationMailer($this->createMailer(self::never())),
        );

        $event = new LoginFailureEvent(
            new AuthenticationException('Invalid credentials.'),
            $this->createStub(AuthenticatorInterface::class),
            Request::create('/v1/auth/login_check', 'POST'),
            null,
            'login',
            null,
        );

        $listener->onLoginFailure($event);
    }

    public function testLoginFailurePersistsLogWhenUserIsAvailable(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $listener = new AuthLogListener(
            $entityManager,
            new RequestStack(),
            $this->createStub(AuthLogRepository::class),
            new LoginNotificationMailer($this->createMailer(self::never())),
        );

        $user = $this->createUserWithId();
        $passport = new SelfValidatingPassport(
            new UserBadge('authlog-failure@example.com', static fn (): User => $user),
        );

        $request = Request::create('/v1/auth/login_check', 'POST');
        $request->headers->set('User-Agent', 'TestAgent');
        $request->server->set('REMOTE_ADDR', '203.0.113.11');

        $event = new LoginFailureEvent(
            new AuthenticationException('Invalid credentials.'),
            $this->createStub(AuthenticatorInterface::class),
            $request,
            null,
            'login',
            $passport,
        );

        $listener->onLoginFailure($event);
    }

    public function testTwoFactorFailurePersistsLog(): void
    {
        $request = Request::create('/v1/auth/login_check', 'POST');
        $request->headers->set('User-Agent', 'TestAgent');
        $request->server->set('REMOTE_ADDR', '203.0.113.10');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted = $entity;
            })
        ;
        $entityManager->expects(self::once())->method('flush');

        $listener = new AuthLogListener(
            $entityManager,
            $requestStack,
            $this->createStub(AuthLogRepository::class),
            new LoginNotificationMailer($this->createMailer(self::never())),
        );

        $listener->onTwoFactorFailure(new TwoFactorFailureEvent($this->createUserWithId()));

        self::assertInstanceOf(AuthLog::class, $persisted);
        self::assertSame(AuthLogAction::TWO_FACTOR_FAILURE, $persisted->getAction());
        self::assertSame('203.0.113.10', $persisted->getIp());
    }

    public function testTwoFactorFailureSkipsWithoutARequest(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $listener = new AuthLogListener(
            $entityManager,
            new RequestStack(),
            $this->createStub(AuthLogRepository::class),
            new LoginNotificationMailer($this->createMailer(self::never())),
        );

        $listener->onTwoFactorFailure(new TwoFactorFailureEvent($this->createUserWithId()));
    }

    private function createUserWithId(): User
    {
        $user = new User();
        $user->setEmail('authlog@example.com');
        $user->setName('Auth Log User');
        $idProperty = new \ReflectionProperty($user, 'id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, Uuid::v4());

        return $user;
    }

    private function createMailer(InvokedCount $rule): MailerInterface
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($rule)->method('send')->with(self::isInstanceOf(RawMessage::class));

        return $mailer;
    }
}
