<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User\BlocklistEntry;

/**
 * @internal
 *
 * @coversNothing
 */
final class BlocklistEndpointsTest extends ApiIntegrationTestCase
{
    public function testBlocklistCrud(): void
    {
        $this->createAccount('owner@example.com', 'Str0ngPassw0rd!@#');
        $token = $this->login('owner@example.com', 'Str0ngPassw0rd!@#');

        $createResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/blocklist',
            payload: ['pattern' => '*@example.com'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(201, $createResponse->getStatusCode(), (string) $createResponse->getContent());
        $createPayload = json_decode((string) $createResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('*@example.com', $createPayload['pattern'] ?? null);
        self::assertNotEmpty($createPayload['id'] ?? null);

        $duplicateResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/blocklist',
            payload: ['pattern' => '*@example.com'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(409, $duplicateResponse->getStatusCode(), (string) $duplicateResponse->getContent());

        // An entry made anywhere, by a command or an earlier version, is on the
        // same list.
        $storedEntry = new BlocklistEntry('*@stored.example');
        $this->entityManager->persist($storedEntry);
        $this->entityManager->flush();

        $listResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/blocklist',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $listResponse->getStatusCode(), (string) $listResponse->getContent());
        self::assertStringContainsString('*@example.com', (string) $listResponse->getContent());
        self::assertStringContainsString('*@stored.example', (string) $listResponse->getContent());

        $deleteResponse = $this->requestJson(
            method: 'DELETE',
            uri: '/v1/blocklist/'.$createPayload['id'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $deleteResponse->getStatusCode());
        self::assertNull($this->entityManager->getRepository(BlocklistEntry::class)->find($createPayload['id']));
    }

    public function testBlocklistKeepsAnsweringOnItsOldPath(): void
    {
        $this->createAccount('legacy-path@example.com', 'Str0ngPassw0rd!@#');
        $token = $this->login('legacy-path@example.com', 'Str0ngPassw0rd!@#');

        $createResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/user/blocklist',
            payload: ['pattern' => '*@legacy-path.example'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(201, $createResponse->getStatusCode(), (string) $createResponse->getContent());

        $listResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/user/blocklist',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $listResponse->getStatusCode(), (string) $listResponse->getContent());
        self::assertStringContainsString('*@legacy-path.example', (string) $listResponse->getContent());
    }

    public function testBlocklistCreateAllowsMultipleEntries(): void
    {
        $this->createAccount('limited@example.com', 'Str0ngPassw0rd!@#');
        $token = $this->login('limited@example.com', 'Str0ngPassw0rd!@#');

        $firstResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/blocklist',
            payload: ['pattern' => '*@first.example'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(201, $firstResponse->getStatusCode(), (string) $firstResponse->getContent());

        $secondResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/blocklist',
            payload: ['pattern' => '*@second.example'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(201, $secondResponse->getStatusCode(), (string) $secondResponse->getContent());
    }

    public function testBlocklistCreateRejectsCommaSeparatedPatterns(): void
    {
        $this->createAccount('invalid-pattern@example.com', 'Str0ngPassw0rd!@#');
        $token = $this->login('invalid-pattern@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/blocklist',
            payload: ['pattern' => '*@example.com,foobar.com'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testBlocklistCreateRejectsInvalidDomainCharacters(): void
    {
        $this->createAccount('invalid-domain-pattern@example.com', 'Str0ngPassw0rd!@#');
        $token = $this->login('invalid-domain-pattern@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/blocklist',
            payload: ['pattern' => 'asd@asd/example.com'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testBlocklistCreateRejectsUniversalPattern(): void
    {
        $this->createAccount('universal-pattern@example.com', 'Str0ngPassw0rd!@#');
        $token = $this->login('universal-pattern@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/blocklist',
            payload: ['pattern' => '*@*.*'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testBlocklistCreateRejectsUniversalPatternWithMultipleDomainLabels(): void
    {
        $this->createAccount('universal-pattern-multi@example.com', 'Str0ngPassw0rd!@#');
        $token = $this->login('universal-pattern-multi@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/blocklist',
            payload: ['pattern' => '*@*.*.*'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testBlocklistBulkDeleteRemovesTheChosenEntries(): void
    {
        $this->createAccount('blocklist-bulk@example.com', 'Str0ngPassw0rd!@#');

        $firstEntry = new BlocklistEntry('*@bulk-1.example');
        $secondEntry = new BlocklistEntry('*@bulk-2.example');
        $keptEntry = new BlocklistEntry('*@bulk-kept.example');

        $this->entityManager->persist($firstEntry);
        $this->entityManager->persist($secondEntry);
        $this->entityManager->persist($keptEntry);
        $this->entityManager->flush();

        $token = $this->login('blocklist-bulk@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'DELETE',
            uri: '/v1/blocklist',
            payload: [
                'ids' => [
                    $firstEntry->getId()?->toRfc4122(),
                    $secondEntry->getId()?->toRfc4122(),
                ],
            ],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2, $payload['deletedCount'] ?? null);

        self::assertNull($this->entityManager->getRepository(BlocklistEntry::class)->find($firstEntry->getId()));
        self::assertNull($this->entityManager->getRepository(BlocklistEntry::class)->find($secondEntry->getId()));
        self::assertNotNull($this->entityManager->getRepository(BlocklistEntry::class)->find($keptEntry->getId()));
    }

    public function testBlocklistBulkDeleteDeletesNothingWhenAnEntryDoesNotExist(): void
    {
        $this->createAccount('blocklist-bulk-missing@example.com', 'Str0ngPassw0rd!@#');

        $entry = new BlocklistEntry('*@bulk-missing.example');
        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        $token = $this->login('blocklist-bulk-missing@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'DELETE',
            uri: '/v1/blocklist',
            payload: [
                'ids' => [
                    $entry->getId()?->toRfc4122(),
                    '00000000-0000-7000-8000-000000000000',
                ],
            ],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(404, $response->getStatusCode(), (string) $response->getContent());
        self::assertNotNull($this->entityManager->getRepository(BlocklistEntry::class)->find($entry->getId()));
    }
}
