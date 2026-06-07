<?php

declare(strict_types=1);

namespace App\MessageHandler\DMARC;

final class TestDnsRecordStore
{
    /**
     * @var array<int, array<string, string>>
     */
    public static array $records = [];

    /**
     * @param array<int, array<string, string>> $records
     */
    public static function setRecords(array $records): void
    {
        self::$records = $records;
    }
}

function dns_get_record(string $hostname, int $type): array
{
    return TestDnsRecordStore::$records;
}

namespace App\Tests\MessageHandler\DMARC;

use App\Entity\DMARC\Domain;
use App\Enum\DMARC\Domain\ProtectionLevel;
use App\Message\DMARC\ProcessDomain;
use App\MessageHandler\DMARC\ProcessDomainHandler;
use App\MessageHandler\DMARC\TestDnsRecordStore;
use App\Repository\DMARC\DomainRepository;
use App\Service\Email\AggregateReportPostboxAddressProvider;
use App\Tests\Api\ApiIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 */
#[CoversClass(ProcessDomainHandler::class)]
final class ProcessDomainHandlerTest extends ApiIntegrationTestCase
{
    public function testInvokeUpdatesDomainWhenDmarcRecordFound(): void
    {
        $token = 'shared-token-'.uniqid('', true);
        $user = $this->createVerifiedUser('domain-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');
        $user->setSharedPostboxIdentifierToken($token);
        $this->entityManager->flush();

        $domain = new Domain($user, 'example.com');
        $this->entityManager->persist($domain);
        $this->entityManager->flush();

        TestDnsRecordStore::setRecords([
            [
                'txt' => sprintf('v=DMARC1; p=quarantine; rua=mailto:%s@reports.example.test', $token),
            ],
        ]);

        $handler = new ProcessDomainHandler(
            self::getContainer()->get(DomainRepository::class),
            $this->entityManager,
            new AggregateReportPostboxAddressProvider('reports.example.test')
        );

        $handler(new ProcessDomain($domain->getId()->toRfc4122()));

        $this->entityManager->refresh($domain);

        self::assertNotNull($domain->getLastChecked());
        self::assertSame(sprintf('v=DMARC1; p=quarantine; rua=mailto:%s@reports.example.test', $token), $domain->getDmarcRecord());
        self::assertSame(ProtectionLevel::MODERATE, $domain->getProtectionLevel());
        self::assertTrue($domain->isConfiguredCorrectly());
    }

    public function testInvokeKeepsDmarcFieldsWhenNoRecordFound(): void
    {
        $user = $this->createVerifiedUser('domain-empty-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');
        $user->setSharedPostboxIdentifierToken('shared-token-'.uniqid('', true));
        $this->entityManager->flush();

        $domain = new Domain($user, 'example.net');
        $this->entityManager->persist($domain);
        $this->entityManager->flush();

        TestDnsRecordStore::setRecords([]);

        $handler = new ProcessDomainHandler(
            self::getContainer()->get(DomainRepository::class),
            $this->entityManager,
            new AggregateReportPostboxAddressProvider('reports.example.test')
        );

        $handler(new ProcessDomain($domain->getId()->toRfc4122()));

        $this->entityManager->refresh($domain);

        self::assertNotNull($domain->getLastChecked());
        self::assertNull($domain->getDmarcRecord());
        self::assertNull($domain->getProtectionLevel());
        self::assertNull($domain->isConfiguredCorrectly());
    }

    public function testInvokeReturnsWhenDomainMissing(): void
    {
        TestDnsRecordStore::setRecords([]);

        $handler = new ProcessDomainHandler(
            self::getContainer()->get(DomainRepository::class),
            $this->entityManager,
            new AggregateReportPostboxAddressProvider('reports.example.test')
        );

        $handler(new ProcessDomain('00000000-0000-0000-0000-000000000000'));

        self::assertTrue(true);
    }
}
