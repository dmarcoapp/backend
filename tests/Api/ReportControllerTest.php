<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\DMARC\Report;
use App\Entity\Email\Email;
use App\Entity\User\User;

/**
 * @internal
 *
 * @coversNothing
 */
final class ReportControllerTest extends ApiIntegrationTestCase
{
    public function testListFiltersByUserAndPagination(): void
    {
        $user = $this->createVerifiedUser('reports@example.com', 'Str0ngPassw0rd!@#');
        $other = $this->createVerifiedUser('other-reports@example.com', 'Str0ngPassw0rd!@#');

        $this->createReport($user, 'example.com', 5, true);
        $this->createReport($user, 'other.com', 2, false);
        $this->createReport($other, 'example.com', 7, true);

        $token = $this->login('reports@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'GET',
            uri: '/v1/dmarc/reports?filter[eq:domain]=example.com&sort[sumCount]=desc',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(1, $payload['meta']['totalCount'] ?? null);
        self::assertCount(1, $payload['items'] ?? []);
        self::assertSame('reporter@example.com', $payload['items'][0]['fromAddress'] ?? null);
    }

    public function testGetRejectsOtherUserReport(): void
    {
        $user = $this->createVerifiedUser('report-owner@example.com', 'Str0ngPassw0rd!@#');
        $other = $this->createVerifiedUser('report-other@example.com', 'Str0ngPassw0rd!@#');

        $report = $this->createReport($user, 'example.com', 1, true);

        $token = $this->login('report-other@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'GET',
            uri: '/v1/dmarc/reports/'.$report->getId()?->toRfc4122(),
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(403, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testXmlReturnsRawXmlForOwner(): void
    {
        $user = $this->createVerifiedUser('xml-owner@example.com', 'Str0ngPassw0rd!@#');

        $report = $this->createReport($user, 'example.com', 1, true, '<xml>raw</xml>');

        $token = $this->login('xml-owner@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'GET',
            uri: '/v1/dmarc/reports/'.$report->getId()?->toRfc4122().'/xml',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));
        self::assertSame('<xml>raw</xml>', (string) $response->getContent());
    }

    public function testDeleteRemovesOwnedReport(): void
    {
        $user = $this->createVerifiedUser('delete-owner@example.com', 'Str0ngPassw0rd!@#');

        $report = $this->createReport($user, 'example.com', 1, true);
        self::assertNotNull($report->getId());

        $token = $this->login('delete-owner@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'DELETE',
            uri: '/v1/dmarc/reports/'.$report->getId()?->toRfc4122(),
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertNull($this->entityManager->getRepository(Report::class)->find($report->getId()));
        self::assertNotNull($this->entityManager->getRepository(User::class)->find($user->getId()));

        $newToken = $this->login('delete-owner@example.com', 'Str0ngPassw0rd!@#');
        self::assertNotSame('', $newToken);
    }

    public function testDeleteBulkRemovesOwnedReportsByIds(): void
    {
        $owner = $this->createVerifiedUser('bulk-delete-owner@example.com', 'Str0ngPassw0rd!@#');
        $other = $this->createVerifiedUser('bulk-delete-other@example.com', 'Str0ngPassw0rd!@#');

        $ownerReport1 = $this->createReport($owner, 'example.com', 1, true);
        $ownerReport2 = $this->createReport($owner, 'example.org', 2, true);
        $otherReport = $this->createReport($other, 'other.com', 3, true);

        $token = $this->login('bulk-delete-owner@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'DELETE',
            uri: '/v1/dmarc/reports',
            payload: [
                'ids' => [
                    $ownerReport1->getId()?->toRfc4122(),
                    $ownerReport2->getId()?->toRfc4122(),
                ],
            ],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2, $payload['deletedCount'] ?? null);

        self::assertNull($this->entityManager->getRepository(Report::class)->find($ownerReport1->getId()));
        self::assertNull($this->entityManager->getRepository(Report::class)->find($ownerReport2->getId()));
        self::assertNotNull($this->entityManager->getRepository(Report::class)->find($otherReport->getId()));
    }

    public function testDeleteBulkRejectsWhenAnyReportIsNotOwnedByAuthenticatedUser(): void
    {
        $owner = $this->createVerifiedUser('bulk-delete-owner-2@example.com', 'Str0ngPassw0rd!@#');
        $other = $this->createVerifiedUser('bulk-delete-other-2@example.com', 'Str0ngPassw0rd!@#');

        $ownerReport = $this->createReport($owner, 'example.com', 1, true);
        $otherReport = $this->createReport($other, 'other.com', 3, true);

        $token = $this->login('bulk-delete-owner-2@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'DELETE',
            uri: '/v1/dmarc/reports',
            payload: [
                'ids' => [
                    $ownerReport->getId()?->toRfc4122(),
                    $otherReport->getId()?->toRfc4122(),
                ],
            ],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(403, $response->getStatusCode(), (string) $response->getContent());
        self::assertNotNull($this->entityManager->getRepository(Report::class)->find($ownerReport->getId()));
        self::assertNotNull($this->entityManager->getRepository(Report::class)->find($otherReport->getId()));
    }

    private function createReport(User $user, string $domain, int $sumCount, bool $processed, string $rawXml = '<xml/>'): Report
    {
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
            ->setOwner($user)
        ;

        $report = new Report();
        $report
            ->setEmail($email)
            ->setRawXML($rawXml)
            ->setIsProcessed($processed)
            ->setBeginDate(new \DateTimeImmutable('-2 days'))
            ->setEndDate(new \DateTimeImmutable('-1 day'))
            ->setDomain($domain)
            ->setSumCount($sumCount)
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
}
