<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler\Email;

use App\DTO\Input\Email\Email as InboundEmailDto;
use App\Entity\Email\Email;
use App\Entity\Platform\PlatformSettings;
use App\Entity\User\BlocklistEntry;
use App\Message\Email\InboundEmail;
use App\Message\Email\ProcessEmail;
use App\MessageHandler\Email\InboundEmailHandler;
use App\Repository\Email\EmailRepository;
use App\Repository\User\BlocklistEntryRepository;
use App\Service\Email\AggregateReportPostboxResolver;
use App\Service\Email\ReportAttachmentDecoder;
use App\Service\Intake\ReportIngestor;
use App\Service\User\BlocklistMatcher;
use App\Tests\Support\PlatformSettingsTestFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(InboundEmailHandler::class)]
final class InboundEmailHandlerTest extends TestCase
{
    private const string REPORT_XML = '<?xml version="1.0"?><feedback/>';

    public function testInvokeLogsWhenEmailAlreadyHandled(): void
    {
        $emailRepository = $this->createStub(EmailRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $blocklistMatcher = $this->createBlocklistMatcher();
        $logger = $this->createMock(LoggerInterface::class);

        $existingEmail = new Email();
        $emailRepository->method('findOneBy')->willReturn($existingEmail);
        $messageBus->expects(self::never())->method('dispatch');
        $logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Inbound email already handled.',
                self::callback(static fn (array $context): bool => isset($context['messageId'], $context['toAddress']))
            )
        ;

        $handler = new InboundEmailHandler($this->resolver(), new ReportIngestor($emailRepository, $blocklistMatcher, $messageBus, $logger), new ReportAttachmentDecoder(), $logger);

        $handler($this->createInboundEmailMessage());
    }

    public function testInvokeDispatchesProcessEmailOnSuccess(): void
    {
        $emailRepository = $this->createMock(EmailRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $blocklistMatcher = $this->createBlocklistMatcher();
        $logger = $this->createStub(LoggerInterface::class);

        $emailRepository->method('findOneBy')->willReturn(null);

        $savedEmailId = Uuid::v4();
        $emailRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Email $email) use ($savedEmailId): bool {
                $this->setEntityId($email, $savedEmailId);

                return self::REPORT_XML === $email->getAttachmentContent();
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

        $handler = new InboundEmailHandler($this->resolver(), new ReportIngestor($emailRepository, $blocklistMatcher, $messageBus, $logger), new ReportAttachmentDecoder(), $logger);

        $handler($this->createInboundEmailMessage());
    }

    public function testInvokeNormalizesFromAddressBeforeBlocklistAndPersist(): void
    {
        $emailRepository = $this->createMock(EmailRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $blocklistMatcher = $this->createBlocklistMatcher();
        $logger = $this->createStub(LoggerInterface::class);

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

        $handler = new InboundEmailHandler($this->resolver(), new ReportIngestor($emailRepository, $blocklistMatcher, $messageBus, $logger), new ReportAttachmentDecoder(), $logger);

        $handler(
            $this->createInboundEmailMessage(from: '"DMARC Aggregate Report" <dmarcreport@microsoft.com>')
        );
    }

    public function testInvokeLogsWhenRecipientIsNotTheReportAddress(): void
    {
        $resolver = $this->resolver('another-token');
        $emailRepository = $this->createStub(EmailRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $blocklistMatcher = $this->createBlocklistMatcher();
        $logger = $this->createMock(LoggerInterface::class);

        $emailRepository->method('findOneBy')->willReturn(null);

        $messageBus->expects(self::never())->method('dispatch');
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Inbound email is not addressed to the report address.',
                self::callback(static fn (array $context): bool => isset($context['messageId'], $context['toAddress']))
            )
        ;

        $handler = new InboundEmailHandler($resolver, new ReportIngestor($emailRepository, $blocklistMatcher, $messageBus, $logger), new ReportAttachmentDecoder(), $logger);

        $handler($this->createInboundEmailMessage());
    }

    public function testInvokeLogsWhenAttachmentMissing(): void
    {
        $emailRepository = $this->createStub(EmailRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $blocklistMatcher = $this->createBlocklistMatcher();
        $logger = $this->createMock(LoggerInterface::class);

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

        $handler = new InboundEmailHandler($this->resolver(), new ReportIngestor($emailRepository, $blocklistMatcher, $messageBus, $logger), new ReportAttachmentDecoder(), $logger);

        $handler($this->createInboundEmailMessage(attachments: []));
    }

    public function testInvokeKeepsAMessageQueuedBeforeTheUpgrade(): void
    {
        $emailRepository = $this->createMock(EmailRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);

        $emailRepository->method('findOneBy')->willReturn(null);
        $emailRepository->expects(self::never())->method('save');
        $messageBus->expects(self::never())->method('dispatch');

        $handler = new InboundEmailHandler($this->resolver(), new ReportIngestor($emailRepository, $this->createBlocklistMatcher(), $messageBus, $this->createStub(LoggerInterface::class)), new ReportAttachmentDecoder(), $this->createStub(LoggerInterface::class));

        // Failing, rather than returning, is what keeps it in the failed transport.
        $this->expectException(UnrecoverableMessageHandlingException::class);

        $handler($this->createInboundEmailMessage(attachments: [[
            'id' => '4f7c3ef4-5f9c-41fb-a61a-1d6c75f8f0b7',
            'bucket' => 'mail',
            'key' => 'attachments/4f7c3ef4-5f9c-41fb-a61a-1d6c75f8f0b7',
            'filename' => 'report.xml',
            'content_type' => 'application/xml',
        ]]));
    }

    public function testInvokeStopsWhenAttachmentCannotBeDecoded(): void
    {
        $emailRepository = $this->createMock(EmailRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $emailRepository->method('findOneBy')->willReturn(null);
        $emailRepository->expects(self::never())->method('save');
        $messageBus->expects(self::never())->method('dispatch');
        $logger
            ->expects(self::once())
            ->method('error')
            ->with('Inbound email attachment could not be decoded.', self::arrayHasKey('error'))
        ;

        $handler = new InboundEmailHandler($this->resolver(), new ReportIngestor($emailRepository, $this->createBlocklistMatcher(), $messageBus, $logger), new ReportAttachmentDecoder(), $logger);

        $handler($this->createInboundEmailMessage(attachments: [[
            'filename' => 'report.xml',
            'content_type' => 'application/xml',
            'content' => 'not base64!',
        ]]));
    }

    public function testInvokeStopsWhenSenderBlocked(): void
    {
        $emailRepository = $this->createMock(EmailRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $blocklistMatcher = $this->createBlocklistMatcher(patterns: ['sender@example.com']);
        $logger = $this->createMock(LoggerInterface::class);

        $emailRepository->method('findOneBy')->willReturn(null);
        $emailRepository->expects(self::never())->method('save');
        $messageBus->expects(self::never())->method('dispatch');
        $logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Inbound email blocked by the blocklist.',
                self::callback(static fn (array $context): bool => array_key_exists('messageId', $context)
                    && array_key_exists('fromAddress', $context))
            )
        ;

        $handler = new InboundEmailHandler($this->resolver(), new ReportIngestor($emailRepository, $blocklistMatcher, $messageBus, $logger), new ReportAttachmentDecoder(), $logger);

        $handler($this->createInboundEmailMessage());
    }

    public function testInvokeUsesTrimmedFromAddressWhenHeaderIsInvalid(): void
    {
        $emailRepository = $this->createMock(EmailRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $blocklistMatcher = $this->createBlocklistMatcher();
        $logger = $this->createStub(LoggerInterface::class);

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

        $handler = new InboundEmailHandler($this->resolver(), new ReportIngestor($emailRepository, $blocklistMatcher, $messageBus, $logger), new ReportAttachmentDecoder(), $logger);
        $handler($this->createInboundEmailMessage(from: '  invalid-from-address  '));
    }

    private function createInboundEmailMessage(?array $attachments = null, string $from = 'sender@example.com'): InboundEmail
    {
        $attachments ??= [
            [
                'filename' => 'report.xml',
                'content_type' => 'application/xml',
                'content' => base64_encode((string) gzencode(self::REPORT_XML)),
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

    private function resolver(string $token = 'shared-token'): AggregateReportPostboxResolver
    {
        return new AggregateReportPostboxResolver(PlatformSettingsTestFactory::provider(new PlatformSettings($token)));
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
        $entries = array_map(static fn (string $pattern): BlocklistEntry => new BlocklistEntry($pattern), $patterns);

        $repository = $this->createStub(BlocklistEntryRepository::class);
        $repository->method('getBlocklistEntries')->willReturn($entries);

        return new BlocklistMatcher($repository);
    }
}
