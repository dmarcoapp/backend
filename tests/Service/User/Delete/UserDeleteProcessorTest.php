<?php

declare(strict_types=1);

namespace App\Tests\Service\User\Delete;

use App\Entity\User\User;
use App\Repository\User\UserRepository;
use App\Service\User\UserDeleteProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
#[CoversClass(UserDeleteProcessor::class)]
final class UserDeleteProcessorTest extends TestCase
{
    public function testProcessDeletesUser(): void
    {
        $user = (new User())
            ->setEmail('delete-processor@example.com')
            ->setName('Delete Processor')
            ->setSharedPostboxIdentifierToken('delete-processor-token')
        ;

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('delete')
            ->with($user)
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $processor = new UserDeleteProcessor(
            $userRepository,
            $logger,
        );
        $processor->process($user);
    }

    public function testProcessLogsAndThrowsWhenDeleteFails(): void
    {
        $user = (new User())
            ->setEmail('delete-processor-delete-fail@example.com')
            ->setName('Delete Processor Delete Fail')
            ->setSharedPostboxIdentifierToken('delete-processor-delete-fail-token')
        ;

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('delete')
            ->with($user)
            ->willThrowException(new \RuntimeException('delete fail'))
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to delete user.',
                self::callback(static function (array $context): bool {
                    return 'delete-processor-delete-fail@example.com' === $context['email']
                        && isset($context['exception'])
                        && $context['exception'] instanceof \Throwable
                        && array_key_exists('user_id', $context);
                }),
            )
        ;

        $processor = new UserDeleteProcessor(
            $userRepository,
            $logger,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to delete user.');
        $processor->process($user);
    }
}
