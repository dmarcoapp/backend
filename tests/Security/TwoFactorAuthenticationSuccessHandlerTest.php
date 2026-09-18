<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User\User;
use App\Enum\User\TwoFactorMethod;
use App\Event\Auth\TwoFactorFailureEvent;
use App\Helper\Base32Codec;
use App\Security\Authentication\TwoFactorAuthenticationSuccessHandler;
use App\Service\User\TwoFactor\TwoFactorCodeMailer;
use App\Service\User\TwoFactor\TwoFactorSecretGenerator;
use App\Service\User\TwoFactor\TwoFactorSecretManager;
use App\Service\User\TwoFactor\TwoFactorService;
use App\Service\User\TwoFactor\TwoFactorTotpService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Rule\InvokedCount;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * @internal
 */
#[CoversClass(TwoFactorAuthenticationSuccessHandler::class)]
final class TwoFactorAuthenticationSuccessHandlerTest extends TestCase
{
    public function testDelegatesToInnerForNonUser(): void
    {
        $inner = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $inner->expects(self::once())->method('onAuthenticationSuccess')->willReturn(new JsonResponse(['ok' => true]));

        $service = $this->createService();
        $mailer = $this->createMailer(self::never());

        $handler = $this->createHandler($inner, $service, $mailer);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(new class implements UserInterface {
            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void {}

            public function getUserIdentifier(): string
            {
                return 'stub';
            }
        });

        $response = $handler->onAuthenticationSuccess(new Request(), $token);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testMissingCodeSendsEmailAndReturnsUnauthorized(): void
    {
        $inner = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $inner->expects(self::never())->method('onAuthenticationSuccess');

        $service = $this->createService();
        $mailer = $this->createMailer(self::once());

        $handler = $this->createHandler($inner, $service, $mailer);

        $user = new User();
        $user->setEmail('user@example.com');
        $user->setName('User');
        $user->setTwoFactorMethod(TwoFactorMethod::EMAIL);
        $user->setTwoFactorEmailSecret(Base32Codec::encode('email-secret'));

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $response = $handler->onAuthenticationSuccess(new Request(), $token);

        self::assertSame(401, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(401, $payload['code'] ?? null);
        self::assertSame('two_factor_required', $payload['error'] ?? null);
    }

    public function testInvalidCodeReturnsUnauthorized(): void
    {
        $inner = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $inner->expects(self::never())->method('onAuthenticationSuccess');

        $service = $this->createService();
        $mailer = $this->createMailer(self::never());

        $handler = $this->createHandler($inner, $service, $mailer);

        $user = new User();
        $user->setTwoFactorMethod(TwoFactorMethod::EMAIL);
        $user->setTwoFactorEmailSecret(Base32Codec::encode('email-secret'));
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $request = new Request(content: json_encode(['two_factor_code' => '000000'], JSON_THROW_ON_ERROR));

        $response = $handler->onAuthenticationSuccess($request, $token);

        self::assertSame(401, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(401, $payload['code'] ?? null);
        self::assertSame('two_factor_invalid', $payload['error'] ?? null);
    }

    public function testValidCodeDelegatesToInner(): void
    {
        $expected = new JsonResponse(['token' => 'ok']);
        $inner = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $inner->expects(self::once())->method('onAuthenticationSuccess')->willReturn($expected);

        $service = $this->createService();
        $mailer = $this->createMailer(self::never());

        $handler = $this->createHandler($inner, $service, $mailer);

        $user = new User();
        $user->setTwoFactorMethod(TwoFactorMethod::EMAIL);
        $user->setTwoFactorEmailSecret(Base32Codec::encode('email-secret'));
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $code = $service->getEmailCode($user);
        $request = new Request(content: json_encode(['two_factor_code' => $code], JSON_THROW_ON_ERROR));

        $response = $handler->onAuthenticationSuccess($request, $token);

        self::assertSame($expected, $response);
    }

    public function testMissingCodeDoesNotSendEmailWhenAppMethodIsUsed(): void
    {
        $inner = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $inner->expects(self::never())->method('onAuthenticationSuccess');

        $service = $this->createService();
        $mailer = $this->createMailer(self::never());

        $handler = $this->createHandler($inner, $service, $mailer);

        $user = new User();
        $user->setTwoFactorMethod(TwoFactorMethod::APP);
        $user->setTwoFactorAppSecret(Base32Codec::encode('app-secret'));

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $response = $handler->onAuthenticationSuccess(new Request(), $token);

        self::assertSame(401, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('two_factor_required', $payload['error'] ?? null);
    }

    public function testAcceptsCamelCaseTwoFactorCodeField(): void
    {
        $expected = new JsonResponse(['token' => 'ok']);
        $inner = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $inner->expects(self::once())->method('onAuthenticationSuccess')->willReturn($expected);

        $service = $this->createService();
        $mailer = $this->createMailer(self::never());

        $handler = $this->createHandler($inner, $service, $mailer);

        $user = new User();
        $user->setTwoFactorMethod(TwoFactorMethod::EMAIL);
        $user->setTwoFactorEmailSecret(Base32Codec::encode('email-secret'));
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $code = $service->getEmailCode($user);
        $request = new Request(content: json_encode(['twoFactorCode' => $code], JSON_THROW_ON_ERROR));

        $response = $handler->onAuthenticationSuccess($request, $token);

        self::assertSame($expected, $response);
    }

    public function testAcceptsIntegerTwoFactorCodeField(): void
    {
        $inner = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $inner->expects(self::never())->method('onAuthenticationSuccess');

        $service = $this->createService();
        $mailer = $this->createMailer(self::never());

        $handler = $this->createHandler($inner, $service, $mailer);

        $user = new User();
        $user->setTwoFactorMethod(TwoFactorMethod::EMAIL);
        $user->setTwoFactorEmailSecret(Base32Codec::encode('email-secret'));
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $request = new Request(content: json_encode(['two_factor_code' => 123456], JSON_THROW_ON_ERROR));

        $response = $handler->onAuthenticationSuccess($request, $token);
        self::assertSame(401, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('two_factor_invalid', $payload['error'] ?? null);
    }

    public function testInvalidJsonPayloadReturnsTwoFactorRequired(): void
    {
        $inner = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $inner->expects(self::never())->method('onAuthenticationSuccess');

        $service = $this->createService();
        $mailer = $this->createMailer(self::once());

        $handler = $this->createHandler($inner, $service, $mailer);

        $user = new User();
        $user->setEmail('json-invalid@example.com');
        $user->setName('Invalid Json');
        $user->setTwoFactorMethod(TwoFactorMethod::EMAIL);
        $user->setTwoFactorEmailSecret(Base32Codec::encode('email-secret'));

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $response = $handler->onAuthenticationSuccess(new Request(content: '{'), $token);

        self::assertSame(401, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('two_factor_required', $payload['error'] ?? null);
    }

    public function testEmptyStringCodeReturnsTwoFactorRequired(): void
    {
        $inner = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $inner->expects(self::never())->method('onAuthenticationSuccess');

        $service = $this->createService();
        $mailer = $this->createMailer(self::once());

        $handler = $this->createHandler($inner, $service, $mailer);

        $user = new User();
        $user->setEmail('empty-code@example.com');
        $user->setName('Empty Code');
        $user->setTwoFactorMethod(TwoFactorMethod::EMAIL);
        $user->setTwoFactorEmailSecret(Base32Codec::encode('email-secret'));

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $request = new Request(content: json_encode(['two_factor_code' => '   '], JSON_THROW_ON_ERROR));
        $response = $handler->onAuthenticationSuccess($request, $token);

        self::assertSame(401, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('two_factor_required', $payload['error'] ?? null);
    }

    public function testNonArrayJsonPayloadReturnsTwoFactorRequired(): void
    {
        $inner = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $inner->expects(self::never())->method('onAuthenticationSuccess');

        $service = $this->createService();
        $mailer = $this->createMailer(self::once());

        $handler = $this->createHandler($inner, $service, $mailer);

        $user = new User();
        $user->setEmail('scalar-json@example.com');
        $user->setName('Scalar Json');
        $user->setTwoFactorMethod(TwoFactorMethod::EMAIL);
        $user->setTwoFactorEmailSecret(Base32Codec::encode('email-secret'));

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $response = $handler->onAuthenticationSuccess(new Request(content: '1'), $token);

        self::assertSame(401, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('two_factor_required', $payload['error'] ?? null);
    }

    public function testRepeatedInvalidCodesAreThrottled(): void
    {
        $inner = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $inner->expects(self::never())->method('onAuthenticationSuccess');

        $service = $this->createService();
        $handler = $this->createHandler($inner, $service, $this->createMailer(self::never()), $this->createLimiterFactory(3));

        $user = new User();
        $user->setEmail('brute-force@example.com');
        $user->setTwoFactorMethod(TwoFactorMethod::EMAIL);
        $user->setTwoFactorEmailSecret(Base32Codec::encode('email-secret'));
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $request = new Request(content: json_encode(['two_factor_code' => '000000'], JSON_THROW_ON_ERROR));

        for ($attempt = 0; $attempt < 3; ++$attempt) {
            self::assertSame(401, $handler->onAuthenticationSuccess($request, $token)->getStatusCode());
        }

        $response = $handler->onAuthenticationSuccess($request, $token);

        self::assertSame(429, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('two_factor_throttled', $payload['error'] ?? null);
    }

    public function testThrottlingIsPerUser(): void
    {
        $inner = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $inner->expects(self::never())->method('onAuthenticationSuccess');

        $service = $this->createService();
        $handler = $this->createHandler($inner, $service, $this->createMailer(self::never()), $this->createLimiterFactory(1));

        $request = new Request(content: json_encode(['two_factor_code' => '000000'], JSON_THROW_ON_ERROR));

        $exhausted = $this->createTokenForUser('exhausted@example.com');
        self::assertSame(401, $handler->onAuthenticationSuccess($request, $exhausted)->getStatusCode());
        self::assertSame(429, $handler->onAuthenticationSuccess($request, $exhausted)->getStatusCode());

        $other = $this->createTokenForUser('untouched@example.com');
        self::assertSame(401, $handler->onAuthenticationSuccess($request, $other)->getStatusCode());
    }

    public function testInvalidCodeDispatchesFailureEvent(): void
    {
        $inner = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $inner->expects(self::never())->method('onAuthenticationSuccess');

        $user = new User();
        $user->setEmail('logged@example.com');
        $user->setTwoFactorMethod(TwoFactorMethod::EMAIL);
        $user->setTwoFactorEmailSecret(Base32Codec::encode('email-secret'));
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(TwoFactorFailureEvent::class))
            ->willReturnArgument(0)
        ;

        $handler = $this->createHandler(
            $inner,
            $this->createService(),
            $this->createMailer(self::never()),
            null,
            $eventDispatcher,
        );

        $request = new Request(content: json_encode(['two_factor_code' => '000000'], JSON_THROW_ON_ERROR));

        self::assertSame(401, $handler->onAuthenticationSuccess($request, $token)->getStatusCode());
    }

    public function testValidCodeClearsEarlierFailedAttempts(): void
    {
        $inner = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $inner->expects(self::exactly(2))->method('onAuthenticationSuccess')->willReturn(new JsonResponse(['token' => 'ok']));

        $service = $this->createService();
        $handler = $this->createHandler($inner, $service, $this->createMailer(self::never()), $this->createLimiterFactory(2));

        $user = new User();
        $user->setEmail('typo@example.com');
        $user->setTwoFactorMethod(TwoFactorMethod::EMAIL);
        $user->setTwoFactorEmailSecret(Base32Codec::encode('email-secret'));
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $wrong = new Request(content: json_encode(['two_factor_code' => '000000'], JSON_THROW_ON_ERROR));
        // A fresh code each time: accepting one retires it.
        $right = static fn (): Request => new Request(
            content: json_encode(['two_factor_code' => $service->getEmailCode($user)], JSON_THROW_ON_ERROR)
        );

        self::assertSame(401, $handler->onAuthenticationSuccess($wrong, $token)->getStatusCode());
        self::assertSame(200, $handler->onAuthenticationSuccess($right(), $token)->getStatusCode());

        // The successful attempt released the budget the typo had taken.
        self::assertSame(401, $handler->onAuthenticationSuccess($wrong, $token)->getStatusCode());
        self::assertSame(200, $handler->onAuthenticationSuccess($right(), $token)->getStatusCode());
    }

    private function createTokenForUser(string $email): TokenInterface
    {
        $user = new User();
        $user->setEmail($email);
        $user->setTwoFactorMethod(TwoFactorMethod::EMAIL);
        $user->setTwoFactorEmailSecret(Base32Codec::encode('email-secret'));

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    private function createHandler(
        AuthenticationSuccessHandlerInterface $inner,
        TwoFactorService $service,
        MailerInterface $mailer,
        ?RateLimiterFactoryInterface $limiter = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): TwoFactorAuthenticationSuccessHandler {
        return new TwoFactorAuthenticationSuccessHandler(
            $inner,
            $service,
            new TwoFactorCodeMailer($mailer),
            $limiter ?? $this->createLimiterFactory(),
            $eventDispatcher ?? $this->createStub(EventDispatcherInterface::class),
        );
    }

    private function createLimiterFactory(int $limit = 5): RateLimiterFactoryInterface
    {
        return new RateLimiterFactory(
            [
                'id' => 'two_factor',
                'interval' => '15 minutes',
                'limit' => $limit,
                'policy' => 'fixed_window',
            ],
            new InMemoryStorage(),
        );
    }

    private function createService(): TwoFactorService
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $secretManager = new TwoFactorSecretManager(
            $entityManager,
            new TwoFactorSecretGenerator()
        );
        $totpService = new TwoFactorTotpService();

        return new TwoFactorService($secretManager, $totpService);
    }

    private function createMailer(InvokedCount $rule): MailerInterface
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($rule)->method('send')->with(self::isInstanceOf(RawMessage::class));

        return $mailer;
    }
}
