<?php

declare(strict_types=1);

namespace App\Tests\DTO\Output\Common;

use App\DTO\Output\Common\PaginationMetaApi;
use App\Entity\DMARC\Domain;
use App\Entity\DMARC\Report;
use App\Tests\Api\ApiIntegrationTestCase;
use Doctrine\ORM\Tools\Pagination\Paginator;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 */
#[CoversClass(PaginationMetaApi::class)]
final class PaginationMetaApiTest extends ApiIntegrationTestCase
{
    public function testBuildsMetaWithFilterAndSortFields(): void
    {
        $user = $this->createVerifiedUser('meta-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        for ($i = 0; $i < 12; ++$i) {
            $domain = new Domain($user, sprintf('domain-%d.example', $i));
            $this->entityManager->persist($domain);
        }
        $this->entityManager->flush();

        $queryBuilder = $this->entityManager->getRepository(Domain::class)->createQueryBuilder('d');
        $queryBuilder->setMaxResults(10);
        $paginator = new Paginator($queryBuilder->getQuery());

        $meta = new PaginationMetaApi($paginator, Domain::class);

        self::assertSame(2, $meta->pageCount);
        self::assertSame(12, $meta->totalCount);
        self::assertContains('domain', $meta->filterableFields);
        self::assertContains('createdAt', $meta->sortableFields);
    }

    public function testDefaultsToEmptyFieldsForNonInterfaces(): void
    {
        $queryBuilder = $this->entityManager->getRepository(Domain::class)->createQueryBuilder('d');
        $queryBuilder->setMaxResults(10);
        $paginator = new Paginator($queryBuilder->getQuery());

        $meta = new PaginationMetaApi($paginator, DummyMetaTarget::class);

        self::assertSame([], $meta->filterableFields);
        self::assertSame([], $meta->sortableFields);
    }

    public function testExposesRelationFilterFields(): void
    {
        $report = new Report();
        $report
            ->setIsProcessed(false)
            ->setBeginDate(new \DateTimeImmutable('-1 day'))
            ->setEndDate(new \DateTimeImmutable())
            ->setDomain('example.com')
        ;
        $this->entityManager->persist($report);
        $this->entityManager->flush();

        $queryBuilder = $this->entityManager->getRepository(Report::class)->createQueryBuilder('r');
        $queryBuilder->setMaxResults(10);
        $paginator = new Paginator($queryBuilder->getQuery());

        $meta = new PaginationMetaApi($paginator, Report::class);

        self::assertContains('records.sourceIp', $meta->filterableFields);
    }
}

final class DummyMetaTarget {}
