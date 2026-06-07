<?php

declare(strict_types=1);

namespace App\Tests\Controller\Auth;

use App\Controller\Auth\RegistrationController;
use App\DTO\Input\Auth\EmailVerify;
use App\DTO\Input\Auth\Registration;
use App\DTO\Input\Auth\RequestVerificationResend;
use App\Entity\User\User;
use App\Event\User\EmailVerificationResendEvent;
use App\Repository\User\UserRepository;
use App\Service\User\UserFactory;
use App\Tests\Api\ApiIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @internal
 */
#[CoversClass(RegistrationController::class)]
final class RegistrationControllerUnitTest extends ApiIntegrationTestCase
{
    public function testRegisterThrowsWhenRegistrationDisabled(): void
    {
        $email = 'disabled-'.uniqid('', true).'@example.com';
        $controller = $this->createController(registrationEnabled: false);

        $this->expectException(BadRequestHttpException::class);

        $controller->register(
            $this->requestWithIp(),
            new Registration(email: $email, name: 'User', password: 'Str0ngPassw0rd!'),
        );
    }

    public function testRegisterThrowsWhenApiLimited(): void
    {
        $email = 'limited-'.uniqid('', true).'@example.com';
        $controller = $this->createController(
            apiLimiter: $this->createLimiterFactory(false),
            registrationEnabled: true
        );

        $this->expectException(TooManyRequestsHttpException::class);

        $controller->register(
            $this->requestWithIp(),
            new Registration(email: $email, name: 'User', password: 'Str0ngPassw0rd!'),
        );
    }

    public function testRegisterThrowsWhenUserExists(): void
    {
        $email = 'existing-'.uniqid('', true).'@example.com';
        $this->createVerifiedUser($email, 'Str0ngPassw0rd!@#');
        $controller = $this->createController();

        $this->expectException(BadRequestHttpException::class);

        $controller->register(
            $this->requestWithIp(),
            new Registration(email: $email, name: 'User', password: 'Str0ngPassw0rd!'),
        );
    }

    public function testRegisterCreatesUser(): void
    {
        $email = 'created-'.uniqid('', true).'@example.com';
        $controller = $this->createController();

        $response = $controller->register(
            $this->requestWithIp(),
            new Registration(email: $email, name: 'User', password: 'Str0ngPassw0rd!'),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('User created successfully.', (string) $response->getContent());

        $repository = self::getContainer()->get(UserRepository::class);
        self::assertNotNull($repository->findOneBy(['email' => $email]));
    }

    public function testRequestVerificationResendDispatchesWhenUserExists(): void
    {
        $email = 'verify-resend-'.uniqid('', true).'@example.com';
        $this->createVerifiedUser($email, 'Str0ngPassw0rd!@#');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(EmailVerificationResendEvent::class))
            ->willReturnArgument(0)
        ;

        $controller = $this->createController(
            eventDispatcher: $dispatcher
        );

        $response = $controller->requestVerificationResend(
            $this->requestWithIp(),
            new RequestVerificationResend(email: $email),
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testRequestVerificationResendSkipsDispatchWhenUserMissing(): void
    {
        $email = 'missing-'.uniqid('', true).'@example.com';
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $controller = $this->createController(
            eventDispatcher: $dispatcher
        );

        $response = $controller->requestVerificationResend(
            $this->requestWithIp(),
            new RequestVerificationResend(email: $email),
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testVerifyThrowsWhenTokenNotFound(): void
    {
        $email = 'verify-missing-'.uniqid('', true).'@example.com';
        $controller = $this->createController();

        $this->expectException(NotFoundHttpException::class);

        $controller->verify(
            $this->requestWithIp(),
            new EmailVerify(email: $email, token: 'missing'),
        );
    }

    public function testVerifyThrowsWhenAlreadyVerified(): void
    {
        $user = new User();
        $user->setEmail('already-verified-'.uniqid('', true).'@example.com');
        $user->setEmailVerificationToken(null);

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);

        $controller = $this->createController(userRepository: $userRepository);

        $this->expectException(BadRequestHttpException::class);

        $controller->verify(
            $this->requestWithIp(),
            new EmailVerify(email: $user->getEmail() ?? 'user@example.com', token: 'token'),
        );
    }

    public function testVerifyThrowsWhenTokenExpired(): void
    {
        $email = 'expired-'.uniqid('', true).'@example.com';
        $user = $this->createVerifiedUser($email, 'Str0ngPassw0rd!@#');
        $token = 'token-'.uniqid('', true);
        $user->setEmailVerificationToken($token);
        $user->setEmailVerificationTokenExpiresAt(new \DateTimeImmutable('-1 minute'));
        $this->entityManager->flush();

        $controller = $this->createController();

        $this->expectException(UnauthorizedHttpException::class);

        $controller->verify(
            $this->requestWithIp(),
            new EmailVerify(email: $email, token: $token),
        );

        $this->entityManager->refresh($user);
        self::assertSame('token', $user->getEmailVerificationToken());
    }

    public function testVerifyClearsTokenOnSuccess(): void
    {
        $email = 'verify-success-'.uniqid('', true).'@example.com';
        $user = $this->createVerifiedUser($email, 'Str0ngPassw0rd!@#');
        $token = 'token-'.uniqid('', true);
        $user->setEmailVerificationToken($token);
        $user->setEmailVerificationTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $this->entityManager->flush();

        $controller = $this->createController();

        $response = $controller->verify(
            $this->requestWithIp(),
            new EmailVerify(email: $email, token: $token),
        );

        self::assertSame(200, $response->getStatusCode());
        $this->entityManager->refresh($user);
        self::assertNull($user->getEmailVerificationToken());
        self::assertNull($user->getEmailVerificationTokenExpiresAt());
    }

    private function createController(
        ?UserRepository $userRepository = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?RateLimiterFactoryInterface $apiLimiter = null,
        ?RateLimiterFactoryInterface $sendmailLimiter = null,
        bool $registrationEnabled = true,
    ): RegistrationController {
        return new RegistrationController(
            $userRepository ?? self::getContainer()->get(UserRepository::class),
            self::getContainer()->get(UserFactory::class),
            $eventDispatcher ?? $this->createStub(EventDispatcherInterface::class),
            $apiLimiter ?? $this->createLimiterFactory(true),
            $sendmailLimiter ?? $this->createLimiterFactory(true),
            $registrationEnabled
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
        return Request::create('/v1/auth/register', server: ['REMOTE_ADDR' => '127.0.0.1']);
    }
}
