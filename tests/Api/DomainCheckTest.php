<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Controller\DMARC\DomainController;
use App\Entity\DMARC\Domain;
use App\MessageHandler\DMARC\TestDnsRecordStore;

require_once __DIR__.'/../Support/DnsRecordStub.php';

/**
 * @internal
 *
 * @coversNothing
 */
final class DomainCheckTest extends ApiIntegrationTestCase
{
    public function testCheckReadsTheRecordAndReturnsTheDomain(): void
    {
        $this->createAccount('owner-one@example.com', 'Str0ngPassw0rd!@#');
        $token = $this->login('owner-one@example.com', 'Str0ngPassw0rd!@#');

        $domain = new Domain('one.example.com');
        $domain->setLastChecked(new \DateTimeImmutable(sprintf('-%d seconds', DomainController::CHECK_COOLDOWN_SECONDS + 5)));
        $this->entityManager->persist($domain);
        $this->entityManager->flush();

        TestDnsRecordStore::setRecords([['txt' => 'v=DMARC1; p=reject']]);

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/dmarc/domains/'.$domain->getId().'/check',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('v=DMARC1; p=reject', $payload['dmarcRecord']);
        self::assertSame('strong', $payload['protectionLevel']);

        $domain = $this->reloaded($domain);
        self::assertGreaterThan(time() - 5, $domain->getLastChecked()?->getTimestamp());
    }

    public function testCheckWorksForADomainNeverChecked(): void
    {
        $this->createAccount('owner-two@example.com', 'Str0ngPassw0rd!@#');
        $token = $this->login('owner-two@example.com', 'Str0ngPassw0rd!@#');

        $domain = new Domain('two.example.com');
        $this->entityManager->persist($domain);
        $this->entityManager->flush();

        TestDnsRecordStore::setRecords([]);

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/dmarc/domains/'.$domain->getId().'/check',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $response->getStatusCode());
        $domain = $this->reloaded($domain);
        self::assertNotNull($domain->getLastChecked());
    }

    public function testCheckIsRefusedWithinAMinuteOfTheLastOne(): void
    {
        $this->createAccount('owner-three@example.com', 'Str0ngPassw0rd!@#');
        $token = $this->login('owner-three@example.com', 'Str0ngPassw0rd!@#');

        $lastChecked = new \DateTimeImmutable('-20 seconds');
        $domain = new Domain('three.example.com');
        $domain->setLastChecked($lastChecked);
        $this->entityManager->persist($domain);
        $this->entityManager->flush();

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/dmarc/domains/'.$domain->getId().'/check',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(429, $response->getStatusCode());
        $retryAfter = (int) $response->headers->get('Retry-After');
        self::assertGreaterThan(0, $retryAfter);
        self::assertLessThanOrEqual(40, $retryAfter);

        $domain = $this->reloaded($domain);
        self::assertSame($lastChecked->getTimestamp(), $domain->getLastChecked()?->getTimestamp());
    }

    public function testCheckNeedsAnAccount(): void
    {
        $domain = new Domain('four.example.com');
        $this->entityManager->persist($domain);
        $this->entityManager->flush();

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/dmarc/domains/'.$domain->getId().'/check',
        );

        self::assertSame(401, $response->getStatusCode());
    }

    private function reloaded(Domain $domain): Domain
    {
        $this->entityManager->clear();
        $fresh = $this->entityManager->find(Domain::class, $domain->getId());
        self::assertInstanceOf(Domain::class, $fresh);

        return $fresh;
    }
}
