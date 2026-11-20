<?php

declare(strict_types=1);

namespace App\Entity\Intake;

use App\Repository\Intake\MailboxLogEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * What happened when a mailbox was read, for the dashboard to show. Never
 * holds a password or the content of a message.
 */
#[ORM\Entity(repositoryClass: MailboxLogEntryRepository::class)]
#[ORM\Table(name: 'mailbox_log_entry')]
#[ORM\Index(name: 'idx_mailbox_log_entry_mailbox_created', fields: ['mailbox', 'createdAt'])]
class MailboxLogEntry
{
    public const string INFO = 'info';
    public const string WARNING = 'warning';
    public const string ERROR = 'error';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Mailbox $mailbox;

    #[ORM\Column(length: 16)]
    private string $level;

    #[ORM\Column(length: 500)]
    private string $message;

    /**
     * @var array<string, null|scalar>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $context;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, null|scalar> $context
     */
    public function __construct(Mailbox $mailbox, string $level, string $message, array $context = [])
    {
        $this->mailbox = $mailbox;
        $this->level = $level;
        $this->message = mb_substr($message, 0, 500);
        $this->context = $context;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getMailbox(): Mailbox
    {
        return $this->mailbox;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, null|scalar>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
