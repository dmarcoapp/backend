<?php

declare(strict_types=1);

namespace App\Service\Intake;

/**
 * An attachment that is not a report DMARCo may unpack: the wrong kind of file,
 * a damaged or deceptive archive, or one past the size limits.
 */
final class ReportArchiveException extends \RuntimeException {}
