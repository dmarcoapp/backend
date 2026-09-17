<?php

declare(strict_types=1);

namespace App\Tests\Controller\Auth;

use App\Controller\User\LogoutController;
use Lexik\Bundle\JWTAuthenticationBundle\Services\BlockedTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * @internal
 */
#[CoversClass(LogoutController::class)]
final class LogoutControllerTest extends TestCase
{
    public function testLogoutThrowsWhenTokenMissing(): void
    {
        $blockedTokenManager = $this->createStub(BlockedTokenManagerInterface::class);
        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $jwtTokenManager = $this->createStub(JWTTokenManagerInterface::class);
        $tokenExtractor = $this->createStub(TokenExtractorInterface::class);
        $tokenStorage = $this->createStub(TokenStorageInterface::class);

        $controller = new LogoutController(
            $blockedTokenManager,
            $dispatcher,
            $jwtTokenManager,
            $tokenExtractor,
            $tokenStorage
        );

        $tokenStorage->method('getToken')->willReturn(null);

        $this->expectException(AccessDeniedException::class);

        $controller->logout(Request::create('/v1/user/logout'), $dispatcher, $tokenStorage);
    }

    public function testLogoutDispatchesEvent(): void
    {
        $blockedTokenManager = $this->createMock(BlockedTokenManagerInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $jwtTokenManager = $this->createStub(JWTTokenManagerInterface::class);
        $tokenExtractor = $this->createStub(TokenExtractorInterface::class);
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $token = $this->createStub(TokenInterface::class);
        $payload = [
            'exp' => (new \DateTimeImmutable('+1 hour'))->getTimestamp(),
            'jti' => 'token-id',
        ];
        $tokenStorage->method('getToken')->willReturn($token);
        $tokenExtractor->method('extract')->willReturn('jwt');
        $jwtTokenManager->method('parse')->with('jwt')->willReturn($payload);
        $blockedTokenManager
            ->expects(self::once())
            ->method('add')
            ->with($payload)
        ;

        $controller = new LogoutController(
            $blockedTokenManager,
            $dispatcher,
            $jwtTokenManager,
            $tokenExtractor,
            $tokenStorage
        );

        $dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(LogoutEvent::class))
            ->willReturnArgument(0)
        ;

        $response = $controller->logout(Request::create('/v1/user/logout'));

        self::assertSame(200, $response->getStatusCode());
    }
}
