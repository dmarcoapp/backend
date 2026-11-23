<?php

declare(strict_types=1);

namespace App\Service\Intake;

use App\Entity\Intake\ApiIntakeKey;
use App\Entity\Intake\Mailbox;
use App\Enum\Email\ReportSource;

/**
 * A report that arrived, unpacked to XML, whichever way it came in.
 */
final readonly class IncomingReport
{
    public function __construct(
        public ReportSource $source,
        public string $messageId,
        public string $fromAddress,
        public string $toAddress,
        public string $filename,
        public string $xml,
        public ?Mailbox $mailbox = null,
        public ?int $imapUid = null,
        public ?ApiIntakeKey $apiKey = null,
    ) {}
}
