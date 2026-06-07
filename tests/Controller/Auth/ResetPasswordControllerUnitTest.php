<?php

declare(strict_types=1);

namespace App\Tests\Controller\Auth;

use App\Controller\Auth\ResetPasswordController;
use App\DTO\Input\Auth\PasswordReset;
use App\DTO\Input\Auth\RequestPasswordReset;
use App\Entity\User\User;
use App\Event\User\PasswordChangeEvent;
use App\Event\User\PasswordResetEvent;
use App\Repository\User\UserRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @internal
 */
#[CoversClass(ResetPasswordController::class)]
final class ResetPasswordControllerUnitTest extends TestCase
{
    public function testRequestPasswordResetThrowsWhenApiLimited(): void
    {
        $controller = $this->createController(
            apiLimiter: $this->createLimiterFactory(false)
        );

        $this->expectException(TooManyRequestsHttpException::class);

        $controller->requestPasswordReset(
            $this->requestWithIp(),
            new RequestPasswordReset(email: 'user@example.com'),
        );
    }

    public function testRequestPasswordResetThrowsWhenSendmailLimited(): void
    {
        $controller = $this->createController(
            sendmailLimiter: $this->createLimiterFactory(false)
        );

        $this->expectException(TooManyRequestsHttpException::class);

        $controller->requestPasswordReset(
            $this->requestWithIp(),
            new RequestPasswordReset(email: 'user@example.com'),
        );
    }

    public function testRequestPasswordResetDispatchesEventWhenUserExists(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(PasswordResetEvent::class))
            ->willReturnArgument(0)
        ;

        $controller = $this->createController(
            userRepository: $userRepository,
            eventDispatcher: $dispatcher
        );

        $response = $controller->requestPasswordReset(
            $this->requestWithIp(),
            new RequestPasswordReset(email: 'user@example.com'),
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testResetPasswordThrowsWhenUserNotFound(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(null);

        $controller = $this->createController(userRepository: $userRepository);

        $this->expectException(NotFoundHttpException::class);

        $controller->resetPassword(
            $this->requestWithIp(),
            new PasswordReset(email: 'user@example.com', token: 'missing', password: 'NewPassw0rd!'),
        );
    }

    public function testResetPasswordThrowsWhenTokenExpired(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setPasswordResetToken('token');
        $user->setPasswordResetTokenExpiresAt(new \DateTimeImmutable('-1 minute'));

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);
        $userRepository
            ->expects(self::once())
            ->method('save')
            ->with($user)
        ;

        $controller = $this->createController(userRepository: $userRepository);

        $this->expectException(UnauthorizedHttpException::class);

        $controller->resetPassword(
            $this->requestWithIp(),
            new PasswordReset(email: 'user@example.com', token: 'token', password: 'NewPassw0rd!'),
        );

        self::assertNull($user->getPasswordResetToken());
    }

    public function testResetPasswordDispatchesEventOnSuccess(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setPasswordResetToken('token');
        $user->setPasswordResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);
        $userRepository
            ->expects(self::once())
            ->method('save')
            ->with($user)
        ;

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(PasswordChangeEvent::class))
            ->willReturnArgument(0)
        ;

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');

        $controller = $this->createController(
            userRepository: $userRepository,
            eventDispatcher: $dispatcher,
            passwordHasher: $hasher
        );

        $response = $controller->resetPassword(
            $this->requestWithIp(),
            new PasswordReset(email: 'user@example.com', token: 'token', password: 'NewPassw0rd!'),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('hashed', $user->getPassword());
        self::assertNull($user->getPasswordResetToken());
        self::assertNull($user->getPasswordResetTokenExpiresAt());
    }

    private function createController(
        ?UserRepository $userRepository = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?UserPasswordHasherInterface $passwordHasher = null,
        ?RateLimiterFactoryInterface $apiLimiter = null,
        ?RateLimiterFactoryInterface $sendmailLimiter = null,
    ): ResetPasswordController {
        return new ResetPasswordController(
            $userRepository ?? $this->createStub(UserRepository::class),
            $eventDispatcher ?? $this->createStub(EventDispatcherInterface::class),
            $passwordHasher ?? $this->createStub(UserPasswordHasherInterface::class),
            $apiLimiter ?? $this->createLimiterFactory(true),
            $sendmailLimiter ?? $this->createLimiterFactory(true)
        );
    }

    private function createLimiterFactory(bool $accepted): RateLimiterFactoryInterface
    {
        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn(
            new RateLimit(
                $accepted ? 1 : 0,
                new \DateTimeImmutable('+1 hour'),
                $accepted,
                $accepted ? 1 : 0
            )
        );

        $factory = $this->createStub(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        return $factory;
    }

    private function requestWithIp(): Request
    {
        return Request::create('/v1/auth/reset_password', server: ['REMOTE_ADDR' => '127.0.0.1']);
    }
}
