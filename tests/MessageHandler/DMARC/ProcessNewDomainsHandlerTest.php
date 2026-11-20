<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler\DMARC;

use App\Entity\DMARC\Domain;
use App\Message\DMARC\ProcessDomain;
use App\Message\DMARC\ProcessNewDomains;
use App\MessageHandler\DMARC\ProcessNewDomainsHandler;
use App\Repository\DMARC\DomainRepository;
use App\Tests\Api\ApiIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(ProcessNewDomainsHandler::class)]
final class ProcessNewDomainsHandlerTest extends ApiIntegrationTestCase
{
    public function testInvokeDispatchesOnlyUncheckedDomains(): void
    {
        $unchecked = new Domain('unchecked.example');
        $checked = (new Domain('checked.example'))
            ->setLastChecked(new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
        ;

        $this->entityManager->persist($unchecked);
        $this->entityManager->persist($checked);
        $this->entityManager->flush();

        $uncheckedId = $unchecked->getId();
        self::assertInstanceOf(Uuid::class, $uncheckedId);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (object $message) use ($uncheckedId): bool {
                return $message instanceof ProcessDomain
                    && $message->domainId === $uncheckedId->toRfc4122();
            }))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $handler = new ProcessNewDomainsHandler(
            self::getContainer()->get(DomainRepository::class),
            $messageBus,
        );

        $handler(new ProcessNewDomains());
    }

    public function testInvokeSkipsADomainWithoutId(): void
    {
        $domainRepository = $this->createStub(DomainRepository::class);
        $domainRepository->method('getUncheckedDomains')->willReturn([
            new Domain('new.example'),
        ]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $handler = new ProcessNewDomainsHandler($domainRepository, $messageBus);

        $handler(new ProcessNewDomains());
    }
}
