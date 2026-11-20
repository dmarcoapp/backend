<?php

declare(strict_types=1);

namespace App\DTO\Input\Intake;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A new mailbox, or a change to one, where a field left out stays as it is.
 * Every string is refused with a control character in it: none belongs there,
 * and in an IMAP command one could end it early.
 */
final readonly class MailboxInput
{
    public const string CREATE = 'create';

    // A folder name is matched with LIST, where these two are wildcards.
    private const string FOLDER_PATTERN = '/^[^\x00-\x1F\x7F*%]+$/u';
    private const string NO_CONTROL_CHARACTERS = '/^[^\x00-\x1F\x7F]*$/u';

    public function __construct(
        #[Assert\NotBlank(groups: [self::CREATE])]
        #[Assert\Length(max: 100)]
        #[Assert\Regex(pattern: self::NO_CONTROL_CHARACTERS, message: 'Remove the control characters.')]
        public ?string $name = null,
        #[Assert\NotBlank(groups: [self::CREATE])]
        #[Assert\Length(max: 255)]
        #[Assert\AtLeastOneOf([
            new Assert\Hostname(requireTld: false),
            new Assert\Ip(version: Assert\Ip::ALL),
        ])]
        public ?string $host = null,
        #[Assert\Range(min: 1, max: 65535)]
        public ?int $port = null,
        #[Assert\Choice(choices: ['tls', 'starttls'])]
        public ?string $security = null,
        #[Assert\NotBlank(groups: [self::CREATE])]
        #[Assert\Length(max: 255)]
        #[Assert\Regex(pattern: self::NO_CONTROL_CHARACTERS, message: 'Remove the control characters.')]
        public ?string $username = null,
        #[Assert\NotBlank(groups: [self::CREATE])]
        #[Assert\Length(max: 1024)]
        #[Assert\Regex(pattern: '/^[^\x00\r\n]*$/u', message: 'A password cannot hold a line break.')]
        public ?string $password = null,
        #[Assert\Length(max: 255)]
        #[Assert\Regex(pattern: self::FOLDER_PATTERN, message: 'A folder name cannot hold * or %, or control characters.')]
        public ?string $folder = null,
        #[Assert\Length(max: 255)]
        #[Assert\AtLeastOneOf([
            new Assert\Blank(),
            new Assert\Email(mode: Assert\Email::VALIDATION_MODE_STRICT),
        ])]
        public ?string $reportAddress = null,
        #[Assert\Choice(choices: ['mark_seen', 'move', 'delete', 'leave'])]
        public ?string $afterImport = null,
        #[Assert\Length(max: 255)]
        #[Assert\Regex(pattern: self::FOLDER_PATTERN, message: 'A folder name cannot hold * or %, or control characters.')]
        public ?string $moveToFolder = null,
        #[Assert\Type(type: 'bool')]
        public ?bool $enabled = null,
        #[Assert\Range(min: 5, max: 1440)]
        public ?int $pollIntervalMinutes = null,
        #[Assert\Range(min: 0, max: 365)]
        public ?int $firstImportDays = null,
    ) {}

    #[Assert\Callback(groups: ['Default', self::CREATE])]
    public function validateMove(ExecutionContextInterface $context): void
    {
        if ('move' !== $this->afterImport) {
            return;
        }

        $target = trim((string) $this->moveToFolder);
        if ('' === $target) {
            $context->buildViolation('Name the folder to move reports to.')->atPath('moveToFolder')->addViolation();

            return;
        }

        if (null !== $this->folder && $target === trim($this->folder)) {
            $context->buildViolation('Reports cannot be moved to the folder they are read from.')->atPath('moveToFolder')->addViolation();
        }
    }
}
