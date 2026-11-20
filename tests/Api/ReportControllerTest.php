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
    protected function setUp(): void
    {
        parent::setUp();

        $this->removeReportData();
    }

    public function testListFiltersAndPaginates(): void
    {
        $this->createAccount('reports@example.com', 'Str0ngPassw0rd!@#');

        $this->createReport('example.com', 5, true);
        $this->createReport('other.com', 2, false);
        $this->createReport('example.com', 7, true);

        $token = $this->login('reports@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'GET',
            uri: '/v1/dmarc/reports?filter[eq:domain]=example.com&sort[sumCount]=desc',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(2, $payload['meta']['totalCount'] ?? null);
        self::assertCount(2, $payload['items'] ?? []);
        self::assertSame(7, $payload['items'][0]['sumCount'] ?? null);
        self::assertSame(5, $payload['items'][1]['sumCount'] ?? null);
    }

    public function testGetReturnsAReport(): void
    {
        $this->createAccount('report-get@example.com', 'Str0ngPassw0rd!@#');

        $report = $this->createReport('example.com', 1, true);

        $token = $this->login('report-get@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'GET',
            uri: '/v1/dmarc/reports/'.$report->getId()?->toRfc4122(),
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringContainsString('reporter@example.com', (string) $response->getContent());
    }

    public function testXmlReturnsRawXml(): void
    {
        $this->createAccount('xml-owner@example.com', 'Str0ngPassw0rd!@#');

        $report = $this->createReport('example.com', 1, true, '<xml>raw</xml>');

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

    public function testDeleteRemovesAReport(): void
    {
        $user = $this->createAccount('delete-owner@example.com', 'Str0ngPassw0rd!@#');

        $report = $this->createReport('example.com', 1, true);
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

    public function testDeleteBulkRemovesTheChosenReports(): void
    {
        $this->createAccount('bulk-delete@example.com', 'Str0ngPassw0rd!@#');

        $firstReport = $this->createReport('example.com', 1, true);
        $secondReport = $this->createReport('example.org', 2, true);
        $keptReport = $this->createReport('other.com', 3, true);

        $token = $this->login('bulk-delete@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'DELETE',
            uri: '/v1/dmarc/reports',
            payload: [
                'ids' => [
                    $firstReport->getId()?->toRfc4122(),
                    $secondReport->getId()?->toRfc4122(),
                ],
            ],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2, $payload['deletedCount'] ?? null);

        self::assertNull($this->entityManager->getRepository(Report::class)->find($firstReport->getId()));
        self::assertNull($this->entityManager->getRepository(Report::class)->find($secondReport->getId()));
        self::assertNotNull($this->entityManager->getRepository(Report::class)->find($keptReport->getId()));
    }

    public function testDeleteBulkDeletesNothingWhenAReportDoesNotExist(): void
    {
        $this->createAccount('bulk-delete-missing@example.com', 'Str0ngPassw0rd!@#');

        $report = $this->createReport('example.com', 1, true);

        $token = $this->login('bulk-delete-missing@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'DELETE',
            uri: '/v1/dmarc/reports',
            payload: [
                'ids' => [
                    $report->getId()?->toRfc4122(),
                    '00000000-0000-7000-8000-000000000000',
                ],
            ],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(404, $response->getStatusCode(), (string) $response->getContent());
        self::assertNotNull($this->entityManager->getRepository(Report::class)->find($report->getId()));
    }

    private function createReport(string $domain, int $sumCount, bool $processed, string $rawXml = '<xml/>'): Report
    {
        $email = new Email();
        $email
            ->setCreatedAt(new \DateTimeImmutable('-1 day'))
            ->setFromAddress('reporter@example.com')
            ->setToAddress('inbound@example.com')
            ->setMessageId('<message@example.com>')
            ->setAttachmentFilename('report.xml')
            ->setAttachmentContentType('application/xml')
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
