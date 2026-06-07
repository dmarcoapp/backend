<?php

declare(strict_types=1);

namespace App\Tests\EventListener\User;

use App\Entity\User\User;
use App\EventListener\User\UserRateLimitListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(UserRateLimitListener::class)]
final class UserRateLimitListenerTest extends TestCase
{
    public function testRegistersAsKernelRequestListener(): void
    {
        $attributes = (new \ReflectionClass(UserRateLimitListener::class))
            ->getAttributes(AsEventListener::class)
        ;

        self::assertCount(1, $attributes);

        $arguments = $attributes[0]->getArguments();

        self::assertSame(KernelEvents::REQUEST, $arguments['event']);
        self::assertSame('onKernelRequest', $arguments['method']);
    }

    public function testSkipsSubRequest(): void
    {
        $limiterFactory = $this->createMock(RateLimiterFactoryInterface::class);
        $limiterFactory->expects(self::never())->method('create');

        $listener = new UserRateLimitListener(
            $limiterFactory,
            $this->createStub(TokenStorageInterface::class),
        );
        $event = $this->createRequestEvent('/v1/user/profile', Request::METHOD_GET, HttpKernelInterface::SUB_REQUEST);

        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testSkipsOptionsRequest(): void
    {
        $limiterFactory = $this->createMock(RateLimiterFactoryInterface::class);
        $limiterFactory->expects(self::never())->method('create');

        $listener = new UserRateLimitListener(
            $limiterFactory,
            $this->createStub(TokenStorageInterface::class),
        );
        $event = $this->createRequestEvent('/v1/user/profile', Request::METHOD_OPTIONS);

        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testSkipsNonApiPath(): void
    {
        $limiterFactory = $this->createMock(RateLimiterFactoryInterface::class);
        $limiterFactory->expects(self::never())->method('create');

        $listener = new UserRateLimitListener(
            $limiterFactory,
            $this->createStub(TokenStorageInterface::class),
        );
        $event = $this->createRequestEvent('/health', Request::METHOD_GET);

        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testSkipsWhenNoAuthenticationToken(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::once())->method('getToken')->willReturn(null);

        $limiterFactory = $this->createMock(RateLimiterFactoryInterface::class);
        $limiterFactory->expects(self::never())->method('create');

        $listener = new UserRateLimitListener($limiterFactory, $tokenStorage);
        $event = $this->createRequestEvent('/v1/user/profile', Request::METHOD_GET);

        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testSkipsWhenTokenUserIsNotAppUser(): void
    {
        $otherUser = new class implements UserInterface {
            #[\Override]
            public function getRoles(): array
            {
                return [];
            }

            #[\Override]
            public function eraseCredentials(): void {}

            #[\Override]
            public function getUserIdentifier(): string
            {
                return 'external-user';
            }
        };

        $token = $this->createMock(TokenInterface::class);
        $token->expects(self::once())->method('getUser')->willReturn($otherUser);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::once())->method('getToken')->willReturn($token);

        $limiterFactory = $this->createMock(RateLimiterFactoryInterface::class);
        $limiterFactory->expects(self::never())->method('create');

        $listener = new UserRateLimitListener($limiterFactory, $tokenStorage);
        $event = $this->createRequestEvent('/v1/user/profile', Request::METHOD_GET);

        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testConsumesRateLimitForAuthenticatedUser(): void
    {
        $user = new User();
        $userId = Uuid::fromString('11111111-1111-1111-1111-111111111111');
        $idProperty = new \ReflectionProperty($user, 'id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, $userId);

        $token = $this->createMock(TokenInterface::class);
        $token->expects(self::once())->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::once())->method('getToken')->willReturn($token);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->expects(self::once())
            ->method('consume')
            ->with(1)
            ->willReturn(new RateLimit(1, new \DateTimeImmutable('+1 hour'), true, 1))
        ;

        $limiterFactory = $this->createMock(RateLimiterFactoryInterface::class);
        $limiterFactory->expects(self::once())
            ->method('create')
            ->with('11111111-1111-1111-1111-111111111111')
            ->willReturn($limiter)
        ;

        $listener = new UserRateLimitListener($limiterFactory, $tokenStorage);
        $event = $this->createRequestEvent('/v1/user/profile', Request::METHOD_GET);

        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testSkipsWhenAppUserIdIsMissing(): void
    {
        $user = new User();

        $token = $this->createMock(TokenInterface::class);
        $token->expects(self::once())->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::once())->method('getToken')->willReturn($token);

        $limiterFactory = $this->createMock(RateLimiterFactoryInterface::class);
        $limiterFactory->expects(self::never())->method('create');

        $listener = new UserRateLimitListener($limiterFactory, $tokenStorage);
        $event = $this->createRequestEvent('/v1/user/profile', Request::METHOD_GET);

        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testThrowsTooManyRequestsWhenRateLimitRejected(): void
    {
        $user = new User();
        $userId = Uuid::fromString('22222222-2222-2222-2222-222222222222');
        $idProperty = new \ReflectionProperty($user, 'id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, $userId);

        $token = $this->createMock(TokenInterface::class);
        $token->expects(self::once())->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::once())->method('getToken')->willReturn($token);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->expects(self::once())
            ->method('consume')
            ->with(1)
            ->willReturn(new RateLimit(0, new \DateTimeImmutable('+1 hour'), false, 0))
        ;

        $limiterFactory = $this->createMock(RateLimiterFactoryInterface::class);
        $limiterFactory->expects(self::once())
            ->method('create')
            ->with('22222222-2222-2222-2222-222222222222')
            ->willReturn($limiter)
        ;

        $listener = new UserRateLimitListener($limiterFactory, $tokenStorage);
        $event = $this->createRequestEvent('/v1/user/profile', Request::METHOD_GET);

        $this->expectException(TooManyRequestsHttpException::class);
        $listener->onKernelRequest($event);
    }

    private function createRequestEvent(string $path, string $method, int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent(
            $kernel,
            Request::create($path, $method),
            $requestType,
        );
    }
}
