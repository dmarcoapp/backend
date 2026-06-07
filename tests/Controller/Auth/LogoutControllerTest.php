<?php

declare(strict_types=1);

namespace App\Tests\Controller\Auth;

use App\Controller\User\LogoutController;
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
        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $tokenStorage = $this->createStub(TokenStorageInterface::class);

        $controller = new LogoutController(
            $dispatcher,
            $tokenStorage
        );

        $tokenStorage->method('getToken')->willReturn(null);

        $this->expectException(AccessDeniedException::class);

        $controller->logout(Request::create('/v1/user/logout'), $dispatcher, $tokenStorage);
    }

    public function testLogoutDispatchesEvent(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $token = $this->createStub(TokenInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $controller = new LogoutController(
            $dispatcher,
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
