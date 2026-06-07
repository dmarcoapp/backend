<?php

declare(strict_types=1);

namespace App\Tests\Repository\Email;

use App\Entity\DMARC\Report;
use App\Entity\Email\Email;
use App\Entity\User\User;
use App\Repository\Email\EmailRepository;
use App\Tests\Api\ApiIntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class EmailRepositoryTest extends ApiIntegrationTestCase
{
    public function testGetEmailIdsWithoutReportReturnsOnlyMissingReports(): void
    {
        $user = $this->createVerifiedUser('email-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $emailWithoutReport = $this->createEmail($user, new \DateTimeImmutable('-1 day'));
        $this->entityManager->persist($emailWithoutReport);

        $emailWithReport = $this->createEmail($user, new \DateTimeImmutable('-1 day'));
        $report = new Report();
        $report->setEmail($emailWithReport);
        $report->setRawXML('<xml/>');
        $emailWithReport->setReport($report);

        $this->entityManager->persist($emailWithReport);
        $this->entityManager->persist($report);
        $this->entityManager->flush();

        $repository = self::getContainer()->get(EmailRepository::class);
        $ids = $repository->getEmailIdsWithoutReport();
        $idValues = array_map(static fn (mixed $id): string => self::normalizeUuid($id), $ids);

        self::assertContains($emailWithoutReport->getId()?->toRfc4122(), $idValues);
        self::assertNotContains($emailWithReport->getId()?->toRfc4122(), $idValues);
    }

    public function testGetJunkEmailIdsToDeleteReturnsOnlyOldEmptyReports(): void
    {
        $user = $this->createVerifiedUser('email-junk-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $oldEmail = $this->createEmail($user, new \DateTimeImmutable('-10 days'));
        $oldReport = new Report();
        $oldReport->setEmail($oldEmail);
        $oldReport->setRawXML(null);
        $oldEmail->setReport($oldReport);

        $newEmail = $this->createEmail($user, new \DateTimeImmutable('-1 day'));
        $newReport = new Report();
        $newReport->setEmail($newEmail);
        $newReport->setRawXML(null);
        $newEmail->setReport($newReport);

        $xmlEmail = $this->createEmail($user, new \DateTimeImmutable('-10 days'));
        $xmlReport = new Report();
        $xmlReport->setEmail($xmlEmail);
        $xmlReport->setRawXML('<xml/>');
        $xmlEmail->setReport($xmlReport);

        $this->entityManager->persist($oldEmail);
        $this->entityManager->persist($oldReport);
        $this->entityManager->persist($newEmail);
        $this->entityManager->persist($newReport);
        $this->entityManager->persist($xmlEmail);
        $this->entityManager->persist($xmlReport);
        $this->entityManager->flush();

        $repository = self::getContainer()->get(EmailRepository::class);
        $ids = $repository->getJunkEmailIdsToDelete(7);
        $idValues = array_map(static fn (mixed $id): string => self::normalizeUuid($id), $ids);

        self::assertContains($oldEmail->getId()?->toRfc4122(), $idValues);
        self::assertNotContains($newEmail->getId()?->toRfc4122(), $idValues);
        self::assertNotContains($xmlEmail->getId()?->toRfc4122(), $idValues);
    }

    public function testSavePersistsEmail(): void
    {
        $user = $this->createVerifiedUser('email-save-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $email = $this->createEmail($user, new \DateTimeImmutable('-1 day'));

        $repository = self::getContainer()->get(EmailRepository::class);
        $repository->save($email);

        self::assertNotNull($email->getId());
        self::assertSame($email->getId(), $repository->find($email->getId())?->getId());
    }

    public function testDeleteByIdsRemovesEmails(): void
    {
        $user = $this->createVerifiedUser('email-delete-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $emailOne = $this->createEmail($user, new \DateTimeImmutable('-2 days'));
        $emailTwo = $this->createEmail($user, new \DateTimeImmutable('-1 day'));

        $this->entityManager->persist($emailOne);
        $this->entityManager->persist($emailTwo);
        $this->entityManager->flush();

        $repository = self::getContainer()->get(EmailRepository::class);
        $ids = $repository->getEmailIdsWithoutReport();
        $emailOneId = $emailOne->getId()?->toRfc4122();
        $deleteId = null;
        foreach ($ids as $rawId) {
            if (self::normalizeUuid($rawId) === $emailOneId) {
                $deleteId = $rawId;

                break;
            }
        }
        self::assertNotNull($deleteId);

        $repository->deleteByIds([$deleteId]);

        $this->entityManager->clear();
        self::assertNull($repository->find($emailOne->getId()));
        self::assertNotNull($repository->find($emailTwo->getId()));
    }

    public function testGetEmailIdsWithoutReportHonorsLimit(): void
    {
        $user = $this->createVerifiedUser('email-limit-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $emailOne = $this->createEmail($user, new \DateTimeImmutable('-1 day'));
        $emailTwo = $this->createEmail($user, new \DateTimeImmutable('-1 day'));

        $this->entityManager->persist($emailOne);
        $this->entityManager->persist($emailTwo);
        $this->entityManager->flush();

        $repository = self::getContainer()->get(EmailRepository::class);
        $ids = $repository->getEmailIdsWithoutReport(1);

        self::assertCount(1, $ids);
    }

    public function testGetJunkEmailIdsToDeleteUsesMinimumRetention(): void
    {
        $user = $this->createVerifiedUser('email-retention-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $oldEmail = $this->createEmail($user, new \DateTimeImmutable('-2 days'));
        $oldReport = new Report();
        $oldReport->setEmail($oldEmail);
        $oldReport->setRawXML(null);
        $oldEmail->setReport($oldReport);

        $this->entityManager->persist($oldEmail);
        $this->entityManager->persist($oldReport);
        $this->entityManager->flush();

        $repository = self::getContainer()->get(EmailRepository::class);
        $ids = $repository->getJunkEmailIdsToDelete(0);
        $idValues = array_map(static fn (mixed $id): string => self::normalizeUuid($id), $ids);

        self::assertContains($oldEmail->getId()?->toRfc4122(), $idValues);
    }

    private function createEmail(User $user, \DateTimeImmutable $createdAt): Email
    {
        $email = new Email();
        $email
            ->setCreatedAt($createdAt)
            ->setFromAddress('reporter@example.com')
            ->setToAddress('inbound@example.com')
            ->setMessageId('<message@example.com>')
            ->setAttachmentBucket('bucket')
            ->setAttachmentKey('path/report.xml')
            ->setAttachmentFilename('report.xml')
            ->setAttachmentContentType('application/xml')
            ->setOwner($user)
        ;

        return $email;
    }

    private static function normalizeUuid(mixed $value): string
    {
        if ($value instanceof Uuid) {
            return $value->toRfc4122();
        }

        if (is_string($value) && 16 === strlen($value)) {
            return Uuid::fromBinary($value)->toRfc4122();
        }

        return Uuid::fromString((string) $value)->toRfc4122();
    }
}
