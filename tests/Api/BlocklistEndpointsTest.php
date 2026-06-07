<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User\BlocklistEntry;
use App\Entity\User\User;

/**
 * @internal
 *
 * @coversNothing
 */
final class BlocklistEndpointsTest extends ApiIntegrationTestCase
{
    public function testBlocklistCrudAndOwnership(): void
    {
        $owner = $this->createVerifiedUser('owner@example.com', 'Str0ngPassw0rd!@#');
        $other = $this->createVerifiedUser('other@example.com', 'Str0ngPassw0rd!@#');

        $ownerToken = $this->login('owner@example.com', 'Str0ngPassw0rd!@#');
        $otherToken = $this->login('other@example.com', 'Str0ngPassw0rd!@#');

        $createResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/user/blocklist',
            payload: ['pattern' => '*@example.com'],
            headers: $this->authorizeHeaders($ownerToken),
        );

        self::assertSame(201, $createResponse->getStatusCode(), (string) $createResponse->getContent());
        $createPayload = json_decode((string) $createResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('*@example.com', $createPayload['pattern'] ?? null);
        self::assertNotEmpty($createPayload['id'] ?? null);

        $duplicateResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/user/blocklist',
            payload: ['pattern' => '*@example.com'],
            headers: $this->authorizeHeaders($ownerToken),
        );

        self::assertSame(409, $duplicateResponse->getStatusCode(), (string) $duplicateResponse->getContent());

        $listResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/user/blocklist',
            headers: $this->authorizeHeaders($ownerToken),
        );

        self::assertSame(200, $listResponse->getStatusCode(), (string) $listResponse->getContent());
        self::assertStringContainsString('*@example.com', (string) $listResponse->getContent());

        $managedOther = $this->entityManager->getRepository(User::class)->find($other->getId());
        self::assertNotNull($managedOther);

        $otherEntry = new BlocklistEntry($managedOther, '*@other.example');
        $this->entityManager->persist($otherEntry);
        $this->entityManager->flush();

        $listAfterOtherEntryResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/user/blocklist',
            headers: $this->authorizeHeaders($ownerToken),
        );

        self::assertSame(200, $listAfterOtherEntryResponse->getStatusCode(), (string) $listAfterOtherEntryResponse->getContent());
        self::assertStringContainsString('*@example.com', (string) $listAfterOtherEntryResponse->getContent());
        self::assertStringNotContainsString('*@other.example', (string) $listAfterOtherEntryResponse->getContent());

        $deleteForbiddenResponse = $this->requestJson(
            method: 'DELETE',
            uri: '/v1/user/blocklist/'.$createPayload['id'],
            headers: $this->authorizeHeaders($otherToken),
        );

        self::assertSame(403, $deleteForbiddenResponse->getStatusCode());

        $deleteResponse = $this->requestJson(
            method: 'DELETE',
            uri: '/v1/user/blocklist/'.$createPayload['id'],
            headers: $this->authorizeHeaders($ownerToken),
        );

        self::assertSame(200, $deleteResponse->getStatusCode());
        self::assertNull($this->entityManager->getRepository(BlocklistEntry::class)->find($createPayload['id']));
    }

    public function testBlocklistCreateAllowsMultipleEntries(): void
    {
        $user = $this->createVerifiedUser('limited@example.com', 'Str0ngPassw0rd!@#');
        $token = $this->login('limited@example.com', 'Str0ngPassw0rd!@#');

        $firstResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/user/blocklist',
            payload: ['pattern' => '*@first.example'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(201, $firstResponse->getStatusCode(), (string) $firstResponse->getContent());

        $secondResponse = $this->requestJson(
            method: 'POST',
            uri: '/v1/user/blocklist',
            payload: ['pattern' => '*@second.example'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(201, $secondResponse->getStatusCode(), (string) $secondResponse->getContent());
    }

    public function testBlocklistCreateRejectsCommaSeparatedPatterns(): void
    {
        $this->createVerifiedUser('invalid-pattern@example.com', 'Str0ngPassw0rd!@#');
        $token = $this->login('invalid-pattern@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/user/blocklist',
            payload: ['pattern' => '*@example.com,foobar.com'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testBlocklistCreateRejectsInvalidDomainCharacters(): void
    {
        $this->createVerifiedUser('invalid-domain-pattern@example.com', 'Str0ngPassw0rd!@#');
        $token = $this->login('invalid-domain-pattern@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/user/blocklist',
            payload: ['pattern' => 'asd@asd/example.com'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testBlocklistCreateRejectsUniversalPattern(): void
    {
        $this->createVerifiedUser('universal-pattern@example.com', 'Str0ngPassw0rd!@#');
        $token = $this->login('universal-pattern@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/user/blocklist',
            payload: ['pattern' => '*@*.*'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testBlocklistCreateRejectsUniversalPatternWithMultipleDomainLabels(): void
    {
        $this->createVerifiedUser('universal-pattern-multi@example.com', 'Str0ngPassw0rd!@#');
        $token = $this->login('universal-pattern-multi@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/user/blocklist',
            payload: ['pattern' => '*@*.*.*'],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testBlocklistBulkDeleteRemovesOwnedEntriesByIds(): void
    {
        $owner = $this->createVerifiedUser('blocklist-bulk-owner@example.com', 'Str0ngPassw0rd!@#');
        $other = $this->createVerifiedUser('blocklist-bulk-other@example.com', 'Str0ngPassw0rd!@#');

        $ownerEntry1 = new BlocklistEntry($owner, '*@owned-1.example');
        $ownerEntry2 = new BlocklistEntry($owner, '*@owned-2.example');
        $otherEntry = new BlocklistEntry($other, '*@other.example');

        $this->entityManager->persist($ownerEntry1);
        $this->entityManager->persist($ownerEntry2);
        $this->entityManager->persist($otherEntry);
        $this->entityManager->flush();

        $token = $this->login('blocklist-bulk-owner@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'DELETE',
            uri: '/v1/user/blocklist',
            payload: [
                'ids' => [
                    $ownerEntry1->getId()?->toRfc4122(),
                    $ownerEntry2->getId()?->toRfc4122(),
                ],
            ],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2, $payload['deletedCount'] ?? null);

        self::assertNull($this->entityManager->getRepository(BlocklistEntry::class)->find($ownerEntry1->getId()));
        self::assertNull($this->entityManager->getRepository(BlocklistEntry::class)->find($ownerEntry2->getId()));
        self::assertNotNull($this->entityManager->getRepository(BlocklistEntry::class)->find($otherEntry->getId()));
    }

    public function testBlocklistBulkDeleteRejectsWhenAnyEntryIsNotOwnedByAuthenticatedUser(): void
    {
        $owner = $this->createVerifiedUser('blocklist-bulk-owner-2@example.com', 'Str0ngPassw0rd!@#');
        $other = $this->createVerifiedUser('blocklist-bulk-other-2@example.com', 'Str0ngPassw0rd!@#');

        $ownerEntry = new BlocklistEntry($owner, '*@owned.example');
        $otherEntry = new BlocklistEntry($other, '*@other.example');

        $this->entityManager->persist($ownerEntry);
        $this->entityManager->persist($otherEntry);
        $this->entityManager->flush();

        $token = $this->login('blocklist-bulk-owner-2@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'DELETE',
            uri: '/v1/user/blocklist',
            payload: [
                'ids' => [
                    $ownerEntry->getId()?->toRfc4122(),
                    $otherEntry->getId()?->toRfc4122(),
                ],
            ],
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(403, $response->getStatusCode(), (string) $response->getContent());
        self::assertNotNull($this->entityManager->getRepository(BlocklistEntry::class)->find($ownerEntry->getId()));
        self::assertNotNull($this->entityManager->getRepository(BlocklistEntry::class)->find($otherEntry->getId()));
    }
}
