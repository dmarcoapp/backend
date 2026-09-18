<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler\Email;

use App\DTO\Input\Email\Email as InboundEmailDto;
use App\Entity\Email\Email;
use App\Entity\User\BlocklistEntry;
use App\Entity\User\User;
use App\Message\Email\InboundEmail;
use App\Message\Email\ProcessEmail;
use App\MessageHandler\Email\InboundEmailHandler;
use App\Repository\Email\EmailRepository;
use App\Repository\User\BlocklistEntryRepository;
use App\Repository\User\UserRepository;
use App\Service\Email\AggregateReportPostboxResolver;
use App\Service\User\BlocklistMatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(InboundEmailHandler::class)]
final class InboundEmailHandlerTest extends TestCase
{
    public function testInvokeLogsWhenEmailAlreadyHandled(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $emailRepository = $this->createStub(EmailRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $blocklistMatcher = $this->createBlocklistMatcher();
        $logger = $this->createMock(LoggerInterface::class);

        $existingEmail = new Email();
        $emailRepository->method('findOneBy')->willReturn($existingEmail);
        $messageBus->expects(self::never())->method('dispatch');
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Inbound email already handled.',
                self::callback(static fn (array $context): bool => isset($context['messageId'], $context['toAddress']))
            )
        ;

        $handler = new InboundEmailHandler(new AggregateReportPostboxResolver($userRepository), $emailRepository, $messageBus, $blocklistMatcher, $logger);

        $handler($this->createInboundEmailMessage());
    }

    public function testInvokeDispatchesProcessEmailOnSuccess(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $emailRepository = $this->createMock(EmailRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $blocklistMatcher = $this->createBlocklistMatcher();
        $logger = $this->createStub(LoggerInterface::class);

        $user = new User();
        $user->setEmail('owner@example.com');
        $user->setName('Owner');
        $user->setSharedPostboxIdentifierToken('shared-token');

        $userRepository->method('findOneBy')->willReturn($user);
        $emailRepository->method('findOneBy')->willReturn(null);

        $savedEmailId = Uuid::v4();
        $emailRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Email $email) use ($savedEmailId): bool {
                $this->setEntityId($email, $savedEmailId);

                return $email->getOwner() instanceof User;
            }))
        ;

        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (ProcessEmail $message) use ($savedEmailId): bool {
                return $message->emailId === $savedEmailId->toRfc4122();
            }))
            ->willReturnCallback(static fn (ProcessEmail $message): Envelope => new Envelope($message))
        ;

        $handler = new InboundEmailHandler(new AggregateReportPostboxResolver($userRepository), $emailRepository, $messageBus, $blocklistMatcher, $logger);

        $handler($this->createInboundEmailMessage());
    }

    public function testInvokeNormalizesFromAddressBeforeBlocklistAndPersist(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $emailRepository = $this->createMock(EmailRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $blocklistMatcher = $this->createBlocklistMatcher();
        $logger = $this->createStub(LoggerInterface::class);

        $user = new User();
        $user->setEmail('owner@example.com');
        $user->setName('Owner');
        $user->setSharedPostboxIdentifierToken('shared-token');

        $userRepository->method('findOneBy')->willReturn($user);
        $emailRepository->method('findOneBy')->willReturn(null);

        $savedEmailId = Uuid::v4();

        $emailRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Email $email) use ($savedEmailId): bool {
                $this->setEntityId($email, $savedEmailId);

                return 'dmarcreport@microsoft.com' === $email->getFromAddress();
            }))
        ;

        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (ProcessEmail $message) use ($savedEmailId): bool {
                return $message->emailId === $savedEmailId->toRfc4122();
            }))
            ->willReturnCallback(static fn (ProcessEmail $message): Envelope => new Envelope($message))
        ;

        $handler = new InboundEmailHandler(new AggregateReportPostboxResolver($userRepository), $emailRepository, $messageBus, $blocklistMatcher, $logger);

        $handler(
            $this->createInboundEmailMessage(from: '"DMARC Aggregate Report" <dmarcreport@microsoft.com>')
        );
    }

    public function testInvokeLogsWhenUserMissing(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $emailRepository = $this->createStub(EmailRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $blocklistMatcher = $this->createBlocklistMatcher();
        $logger = $this->createMock(LoggerInterface::class);

        $userRepository->method('findOneBy')->willReturn(null);
        $emailRepository->method('findOneBy')->willReturn(null);

        $messageBus->expects(self::never())->method('dispatch');
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'User not found for email.',
                self::callback(static fn (array $context): bool => isset($context['messageId'], $context['toAddress']))
            )
        ;

        $handler = new InboundEmailHandler(new AggregateReportPostboxResolver($userRepository), $emailRepository, $messageBus, $blocklistMatcher, $logger);

        $handler($this->createInboundEmailMessage());
    }

    public function testInvokeLogsWhenAttachmentMissing(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $emailRepository = $this->createStub(EmailRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $blocklistMatcher = $this->createBlocklistMatcher();
        $logger = $this->createMock(LoggerInterface::class);

        $user = new User();
        $user->setEmail('owner@example.com');
        $user->setName('Owner');
        $user->setSharedPostboxIdentifierToken('shared-token');

        $userRepository->method('findOneBy')->willReturn($user);
        $emailRepository->method('findOneBy')->willReturn(null);

        $messageBus->expects(self::never())->method('dispatch');
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'No attachment found for email.',
                self::callback(static fn (array $context): bool => isset($context['messageId'], $context['toAddress']))
            )
        ;

        $handler = new InboundEmailHandler(new AggregateReportPostboxResolver($userRepository), $emailRepository, $messageBus, $blocklistMatcher, $logger);

        $handler($this->createInboundEmailMessage(attachments: []));
    }

    public function testInvokeStopsWhenSenderBlocked(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $emailRepository = $this->createMock(EmailRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $blocklistMatcher = $this->createBlocklistMatcher(patterns: ['sender@example.com']);
        $logger = $this->createMock(LoggerInterface::class);

        $user = new User();
        $user->setEmail('owner@example.com');
        $user->setName('Owner');
        $user->setSharedPostboxIdentifierToken('shared-token');

        $userRepository->method('findOneBy')->willReturn($user);
        $emailRepository->method('findOneBy')->willReturn(null);
        $emailRepository->expects(self::never())->method('save');
        $messageBus->expects(self::never())->method('dispatch');
        $logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Inbound email blocked by user blocklist.',
                self::callback(static fn (array $context): bool => array_key_exists('messageId', $context)
                    && array_key_exists('fromAddress', $context)
                    && array_key_exists('userId', $context))
            )
        ;

        $handler = new InboundEmailHandler(new AggregateReportPostboxResolver($userRepository), $emailRepository, $messageBus, $blocklistMatcher, $logger);

        $handler($this->createInboundEmailMessage());
    }

    public function testInvokeUsesTrimmedFromAddressWhenHeaderIsInvalid(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $emailRepository = $this->createMock(EmailRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $blocklistMatcher = $this->createBlocklistMatcher();
        $logger = $this->createStub(LoggerInterface::class);

        $user = new User();
        $user->setEmail('owner@example.com');
        $user->setName('Owner');
        $user->setSharedPostboxIdentifierToken('shared-token');

        $userRepository->method('findOneBy')->willReturn($user);
        $emailRepository->method('findOneBy')->willReturn(null);

        $savedEmailId = Uuid::v4();
        $emailRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Email $email) use ($savedEmailId): bool {
                $this->setEntityId($email, $savedEmailId);

                return 'invalid-from-address' === $email->getFromAddress();
            }))
        ;
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (ProcessEmail $message): bool => $message->emailId === $savedEmailId->toRfc4122()))
            ->willReturnCallback(static fn (ProcessEmail $message): Envelope => new Envelope($message))
        ;

        $handler = new InboundEmailHandler(new AggregateReportPostboxResolver($userRepository), $emailRepository, $messageBus, $blocklistMatcher, $logger);
        $handler($this->createInboundEmailMessage(from: '  invalid-from-address  '));
    }

    private function createInboundEmailMessage(?array $attachments = null, string $from = 'sender@example.com'): InboundEmail
    {
        $attachments ??= [
            [
                'id' => Uuid::v4()->toRfc4122(),
                'bucket' => 'bucket',
                'key' => 'path/report.xml',
                'filename' => 'report.xml',
                'content_type' => 'application/xml',
            ],
        ];

        return new InboundEmail(
            new InboundEmailDto(
                email_id: 'email-id',
                created_at: new \DateTimeImmutable('2024-01-01 10:00:00'),
                from: $from,
                to: ['shared-token@aggregate-reports.example.test'],
                message_id: '<message@example.com>',
                attachments: $attachments
            )
        );
    }

    private function setEntityId(object $entity, Uuid $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    /**
     * @param list<string> $patterns
     */
    private function createBlocklistMatcher(array $patterns = []): BlocklistMatcher
    {
        $entries = array_map(static function (string $pattern): BlocklistEntry {
            $user = (new User())
                ->setEmail('blocked@example.com')
                ->setSharedPostboxIdentifierToken('blocked-token')
            ;

            return new BlocklistEntry($user, $pattern);
        }, $patterns);

        $repository = $this->createStub(BlocklistEntryRepository::class);
        $repository->method('getBlocklistEntriesForUser')->willReturn($entries);

        return new BlocklistMatcher($repository);
    }
}
