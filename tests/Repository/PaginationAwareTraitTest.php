<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\DTO\Input\Common\Pagination;
use App\Entity\DMARC\Domain;
use App\Entity\DMARC\Report;
use App\Entity\DMARC\ReportRecord;
use App\Entity\Email\Email;
use App\Entity\User\User;
use App\Enum\DMARC\DispositionType;
use App\Enum\DMARC\DKIMAlign;
use App\Enum\DMARC\SPFAlign;
use App\Enum\DMARC\SPFResult;
use App\Repository\PaginationAwareTrait;
use App\Tests\Api\ApiIntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class PaginationAwareTraitTest extends ApiIntegrationTestCase
{
    public function testPaginateCoversFilterOperatorsAndSort(): void
    {
        $user = $this->createVerifiedUser('pagination-trait-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');
        $domain = new Domain($user, 'example.com');
        $domain->setLastChecked(new \DateTimeImmutable('2024-01-01 00:00:00'));
        $this->setPrivateProperty($domain, 'createdAt', new \DateTimeImmutable('2024-01-01 00:00:00'));
        $this->entityManager->persist($domain);
        $this->entityManager->flush();

        $pagination = new Pagination(
            filter: [
                'eq:domain' => 'example.com',
                'neq:domain' => 'example.net',
                'contains:domain' => 'example',
                'not_contains:domain' => 'invalid',
                'in:domain' => 'example.com,example.net',
                'not_in:domain' => 'other.com',
                'lt:domain' => 'zzzz.example.com',
                'lte:domain' => 'zzzz.example.com',
                'gt:domain' => 'aaaa.example.com',
                'gte:domain' => 'aaaa.example.com',
                'eq:unknown' => 'ignored',
            ],
            sort: [
                'domain' => 'asc',
                'unknown' => 'desc',
            ],
        );

        $repository = $this->createPaginationRepository();
        $results = iterator_to_array($repository->run($pagination));

        self::assertIsArray($results);
    }

    public function testPaginateDefaultsToIdSortWhenSortEmpty(): void
    {
        $user = $this->createVerifiedUser('pagination-default-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');
        $domain = new Domain($user, 'default.example.com');
        $domain->setLastChecked(new \DateTimeImmutable('2024-01-01 00:00:00'));
        $this->setPrivateProperty($domain, 'createdAt', new \DateTimeImmutable('2024-01-01 00:00:00'));
        $this->entityManager->persist($domain);
        $this->entityManager->flush();

        $pagination = new Pagination();

        $repository = $this->createPaginationRepository();
        $results = iterator_to_array($repository->run($pagination));

        self::assertNotEmpty($results);
    }

    public function testPaginateThrowsWhenListingQueryBuilderMissing(): void
    {
        $pagination = new Pagination();

        $repository = new class {
            use PaginationAwareTrait;

            public function run(Pagination $pagination): iterable
            {
                return $this->paginate($pagination);
            }

            public function getClassName(): string
            {
                return Domain::class;
            }
        };

        $this->expectException(\BadMethodCallException::class);

        $repository->run($pagination);
    }

    public function testPaginateSupportsRelationFieldFilter(): void
    {
        $user = $this->createVerifiedUser('pagination-relation-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $reportA = $this->createReport($user, 'example.com');
        $reportB = $this->createReport($user, 'other.example');

        $recordA1 = $this->createRecord($reportA, '192.0.2.1');
        $recordA2 = $this->createRecord($reportA, '192.0.2.1');
        $recordB = $this->createRecord($reportB, '192.0.2.2');

        $reportA->addRecord($recordA1);
        $reportA->addRecord($recordA2);
        $reportB->addRecord($recordB);

        $this->entityManager->persist($recordA1);
        $this->entityManager->persist($recordA2);
        $this->entityManager->persist($recordB);
        $this->entityManager->flush();

        $pagination = new Pagination(filter: ['eq:records.sourceIp' => '192.0.2.1']);

        $repository = $this->createReportPaginationRepository();
        $results = iterator_to_array($repository->run($pagination));

        self::assertCount(1, $results);
        self::assertSame('example.com', $results[0]->getDomain());
    }

    public function testPaginateUsesProvidedQueryBuilderWhenPassedExplicitly(): void
    {
        $user = $this->createVerifiedUser('pagination-explicit-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');
        $domain = new Domain($user, 'explicit.example.com');
        $domain->setLastChecked(new \DateTimeImmutable('2024-01-01 00:00:00'));
        $this->setPrivateProperty($domain, 'createdAt', new \DateTimeImmutable('2024-01-01 00:00:00'));
        $this->entityManager->persist($domain);
        $this->entityManager->flush();

        $queryBuilder = $this->entityManager
            ->createQueryBuilder()
            ->select('d')
            ->from(Domain::class, 'd')
        ;

        $repository = new class {
            use PaginationAwareTrait;

            public function getClassName(): string
            {
                return Domain::class;
            }

            public function run(Pagination $pagination, QueryBuilder $queryBuilder): iterable
            {
                return $this->paginate($pagination, $queryBuilder);
            }
        };

        $results = iterator_to_array($repository->run(new Pagination(), $queryBuilder));
        self::assertNotEmpty($results);
    }

    private function createPaginationRepository(): object
    {
        return new class($this->entityManager) {
            use PaginationAwareTrait;

            public function __construct(private EntityManagerInterface $entityManager) {}

            public function getClassName(): string
            {
                return Domain::class;
            }

            public function getListingQueryBuilder(array $context = []): QueryBuilder
            {
                return $this->entityManager
                    ->createQueryBuilder()
                    ->select('d')
                    ->from(Domain::class, 'd')
                ;
            }

            public function run(Pagination $pagination): iterable
            {
                return $this->paginate($pagination);
            }
        };
    }

    private function createReportPaginationRepository(): object
    {
        return new class($this->entityManager) {
            use PaginationAwareTrait;

            public function __construct(private EntityManagerInterface $entityManager) {}

            public function getClassName(): string
            {
                return Report::class;
            }

            public function getListingQueryBuilder(array $context = []): QueryBuilder
            {
                return $this->entityManager
                    ->createQueryBuilder()
                    ->select('r')
                    ->from(Report::class, 'r')
                ;
            }

            public function run(Pagination $pagination): iterable
            {
                return $this->paginate($pagination);
            }
        };
    }

    private function createReport(User $user, string $domain): Report
    {
        $email = new Email();
        $email
            ->setCreatedAt(new \DateTimeImmutable())
            ->setFromAddress('reporter@example.com')
            ->setToAddress('inbound@example.com')
            ->setMessageId('<message@example.com>')
            ->setAttachmentBucket('bucket')
            ->setAttachmentKey('path/report.xml')
            ->setAttachmentFilename('report.xml')
            ->setAttachmentContentType('application/xml')
            ->setOwner($user)
        ;

        $report = new Report();
        $report
            ->setEmail($email)
            ->setRawXML('<xml/>')
            ->setIsProcessed(true)
            ->setBeginDate(new \DateTimeImmutable('-1 day'))
            ->setEndDate(new \DateTimeImmutable())
            ->setDomain($domain)
            ->setSumCount(1)
            ->setDmarcCompliance(1.0)
            ->setSpfCompliance(1.0)
            ->setDkimCompliance(1.0)
        ;

        $email->setReport($report);

        $this->entityManager->persist($email);
        $this->entityManager->persist($report);
        $this->entityManager->flush();

        return $report;
    }

    private function createRecord(Report $report, string $sourceIp): ReportRecord
    {
        return (new ReportRecord())
            ->setReport($report)
            ->setSourceIp($sourceIp)
            ->setCount(1)
            ->setDisposition(DispositionType::NONE)
            ->setDkimAlign(DKIMAlign::PASS)
            ->setSpfAlign(SPFAlign::PASS)
            ->setSpfAuth(SPFResult::PASS)
        ;
    }

    private function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($object, $value);
    }
}
