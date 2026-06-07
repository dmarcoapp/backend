<?php

declare(strict_types=1);

namespace App\Tests\Repository\DMARC;

use App\DTO\Input\Common\Pagination;
use App\Entity\DMARC\Domain;
use App\Repository\DMARC\DomainRepository;
use App\Tests\Api\ApiIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 */
#[CoversClass(DomainRepository::class)]
final class DomainRepositoryTest extends ApiIntegrationTestCase
{
    public function testGetDomainsForUserPaginatedAppliesFilterAndSort(): void
    {
        $user = $this->createVerifiedUser('domain-pagination-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $domainA = new Domain($user, 'alpha.example.com');
        $domainA->setLastChecked(new \DateTimeImmutable('2024-01-01 00:00:00'));
        $domainB = new Domain($user, 'beta.test.com');
        $domainB->setLastChecked(new \DateTimeImmutable('2024-01-01 00:00:00'));
        $domainC = new Domain($user, 'zeta.example.com');
        $domainC->setLastChecked(new \DateTimeImmutable('2024-01-01 00:00:00'));

        $this->entityManager->persist($domainA);
        $this->entityManager->persist($domainB);
        $this->entityManager->persist($domainC);
        $this->entityManager->flush();

        $pagination = new Pagination(
            filter: [
                'contains:domain' => 'example.com',
                'eq:unknown' => 'ignored',
            ],
            sort: [
                'domain' => 'desc',
                'unknown' => 'asc',
            ],
        );

        $repository = self::getContainer()->get(DomainRepository::class);
        $paginator = $repository->getDomainsForUserPaginated($user, $pagination);
        $results = iterator_to_array($paginator);

        self::assertCount(2, $results);
        self::assertSame('zeta.example.com', $results[0]->getDomain());
        self::assertSame('alpha.example.com', $results[1]->getDomain());
    }
}
