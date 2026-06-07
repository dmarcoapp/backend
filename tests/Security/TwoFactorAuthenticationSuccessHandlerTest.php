<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User\User;
use App\Enum\User\TwoFactorMethod;
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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
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

        $handler = new TwoFactorAuthenticationSuccessHandler($inner, $service, new TwoFactorCodeMailer($mailer));

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

        $handler = new TwoFactorAuthenticationSuccessHandler($inner, $service, new TwoFactorCodeMailer($mailer));

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

        $handler = new TwoFactorAuthenticationSuccessHandler($inner, $service, new TwoFactorCodeMailer($mailer));

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

        $handler = new TwoFactorAuthenticationSuccessHandler($inner, $service, new TwoFactorCodeMailer($mailer));

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

        $handler = new TwoFactorAuthenticationSuccessHandler($inner, $service, new TwoFactorCodeMailer($mailer));

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

        $handler = new TwoFactorAuthenticationSuccessHandler($inner, $service, new TwoFactorCodeMailer($mailer));

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

        $handler = new TwoFactorAuthenticationSuccessHandler($inner, $service, new TwoFactorCodeMailer($mailer));

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

        $handler = new TwoFactorAuthenticationSuccessHandler($inner, $service, new TwoFactorCodeMailer($mailer));

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

        $handler = new TwoFactorAuthenticationSuccessHandler($inner, $service, new TwoFactorCodeMailer($mailer));

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

        $handler = new TwoFactorAuthenticationSuccessHandler($inner, $service, new TwoFactorCodeMailer($mailer));

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
