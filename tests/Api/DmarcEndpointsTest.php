<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\DMARC\Domain;
use App\Entity\DMARC\Report;
use App\Entity\Email\Email;

/**
 * @internal
 *
 * @coversNothing
 */
final class DmarcEndpointsTest extends ApiIntegrationTestCase
{
    public function testDomainsAndReportsRequireOwnership(): void
    {
        $owner = $this->createVerifiedUser('owner@example.com', 'Str0ngPassw0rd!@#');
        $other = $this->createVerifiedUser('other@example.com', 'Str0ngPassw0rd!@#');
        self::assertNotNull($owner->getId());
        self::assertTrue($this->entityManager->contains($owner));

        $domain = new Domain($owner, 'example.com');
        $domain->setLastChecked(new \DateTimeImmutable());
        $this->entityManager->persist($domain);

        $email = new Email();
        $email
            ->setCreatedAt(new \DateTimeImmutable('-1 day'))
            ->setFromAddress('reporter@example.com')
            ->setToAddress('inbound@example.com')
            ->setMessageId('<message@example.com>')
            ->setAttachmentBucket('bucket')
            ->setAttachmentKey('path/report.xml')
            ->setAttachmentFilename('report.xml')
            ->setAttachmentContentType('application/xml')
            ->setOwner($owner)
        ;

        $report = new Report();
        $report
            ->setEmail($email)
            ->setRawXML('<xml/>')
            ->setIsProcessed(true)
            ->setBeginDate(new \DateTimeImmutable('-2 days'))
            ->setEndDate(new \DateTimeImmutable('-1 day'))
            ->setDomain('example.com')
            ->setSumCount(1)
            ->setDmarcCompliance(1.0)
            ->setSpfCompliance(1.0)
            ->setDkimCompliance(1.0)
        ;

        $email->setReport($report);

        $this->entityManager->persist($email);
        $this->entityManager->persist($report);
        $this->entityManager->flush();
        self::assertNotNull($domain->getId());

        self::assertCount(1, $this->entityManager->getRepository(Domain::class)->findAll());

        $ownerToken = $this->login('owner@example.com', 'Str0ngPassw0rd!@#');
        $otherToken = $this->login('other@example.com', 'Str0ngPassw0rd!@#');

        $domainsResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/dmarc/domains',
            headers: $this->authorizeHeaders($ownerToken),
        );

        self::assertSame(200, $domainsResponse->getStatusCode());
        self::assertStringContainsString('example.com', (string) $domainsResponse->getContent());

        $domainGetResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/dmarc/domains/'.$domain->getId(),
            headers: $this->authorizeHeaders($ownerToken),
        );

        self::assertSame(200, $domainGetResponse->getStatusCode());

        $domainForbiddenResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/dmarc/domains/'.$domain->getId(),
            headers: $this->authorizeHeaders($otherToken),
        );

        self::assertSame(403, $domainForbiddenResponse->getStatusCode());

        $reportsResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/dmarc/reports',
            headers: $this->authorizeHeaders($ownerToken),
        );

        self::assertSame(200, $reportsResponse->getStatusCode());
        self::assertStringContainsString('example.com', (string) $reportsResponse->getContent());

        $reportGetResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/dmarc/reports/'.$report->getId(),
            headers: $this->authorizeHeaders($ownerToken),
        );

        self::assertSame(200, $reportGetResponse->getStatusCode());

        $reportForbiddenResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/dmarc/reports/'.$report->getId(),
            headers: $this->authorizeHeaders($otherToken),
        );

        self::assertSame(403, $reportForbiddenResponse->getStatusCode());

        $reportDeleteForbiddenResponse = $this->requestJson(
            method: 'DELETE',
            uri: '/v1/dmarc/reports/'.$report->getId(),
            headers: $this->authorizeHeaders($otherToken),
        );

        self::assertSame(403, $reportDeleteForbiddenResponse->getStatusCode());

        $reportXmlForbiddenResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/dmarc/reports/'.$report->getId().'/xml',
            headers: $this->authorizeHeaders($otherToken),
        );

        self::assertSame(403, $reportXmlForbiddenResponse->getStatusCode());

        $reportRecordsForbiddenResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/dmarc/reports/'.$report->getId().'/records',
            headers: $this->authorizeHeaders($otherToken),
        );

        self::assertSame(403, $reportRecordsForbiddenResponse->getStatusCode());

        $reportXmlResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/dmarc/reports/'.$report->getId().'/xml',
            headers: $this->authorizeHeaders($ownerToken),
        );

        self::assertSame(200, $reportXmlResponse->getStatusCode());
        self::assertSame('<xml/>', (string) $reportXmlResponse->getContent());

        $reportDeleteResponse = $this->requestJson(
            method: 'DELETE',
            uri: '/v1/dmarc/reports/'.$report->getId(),
            headers: $this->authorizeHeaders($ownerToken),
        );

        self::assertSame(200, $reportDeleteResponse->getStatusCode());
    }
}
