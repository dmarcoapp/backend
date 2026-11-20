<?php

declare(strict_types=1);

namespace App\Service\Intake;

/**
 * A mailbox that is reachable but not usable as configured.
 */
final class MailboxException extends \RuntimeException {}
