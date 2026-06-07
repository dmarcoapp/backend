<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler\User;

use App\Entity\DMARC\Domain;
use App\Entity\User\User;
use App\Message\DMARC\ProcessDomain;
use App\Message\User\ProcessUserHourly;
use App\MessageHandler\User\ProcessUserHourlyHandler;
use App\Repository\DMARC\DomainRepository;
use App\Repository\User\UserRepository;
use App\Tests\Api\ApiIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(ProcessUserHourlyHandler::class)]
final class SearchNewDomainsForUserHandlerTest extends ApiIntegrationTestCase
{
    public function testInvokeDispatchesOnlyUncheckedDomains(): void
    {
        $user = $this->createVerifiedUser('search-domains-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $unchecked = new Domain($user, 'unchecked.example');
        $checked = (new Domain($user, 'checked.example'))
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

        $handler = new ProcessUserHourlyHandler(
            self::getContainer()->get(UserRepository::class),
            self::getContainer()->get(DomainRepository::class),
            $messageBus,
        );

        $handler(new ProcessUserHourly($user->getId()->toRfc4122()));
    }

    public function testInvokeReturnsWhenUserMissing(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $handler = new ProcessUserHourlyHandler(
            self::getContainer()->get(UserRepository::class),
            self::getContainer()->get(DomainRepository::class),
            $messageBus,
        );

        $handler(new ProcessUserHourly('00000000-0000-0000-0000-000000000000'));

        self::assertTrue(true);
    }

    public function testProcessDomainsSkipsDispatchWhenDomainIdIsMissing(): void
    {
        $user = new User();
        $user->setEmail('no-id-user@example.com');
        $user->setSharedPostboxIdentifierToken('token');

        $userRepository = $this->createStub(UserRepository::class);

        $domainRepository = $this->createStub(DomainRepository::class);
        $domainRepository->method('getUncheckedDomainsForUser')->with($user)->willReturn([
            new Domain($user, 'new.example'),
        ]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $handler = new ProcessUserHourlyHandler(
            $userRepository,
            $domainRepository,
            $messageBus,
        );

        $handler->processDomains($user);
    }
}
