<?php

declare(strict_types=1);

namespace App\Tests\Message;

use App\Message\DMARC\ProcessDomain;
use App\Message\DMARC\ProcessReport;
use App\Message\Email\ProcessEmail;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ProcessDomain::class)]
#[CoversClass(ProcessReport::class)]
#[CoversClass(ProcessEmail::class)]
final class MessageSmokeTest extends TestCase
{
    public function testMessageValues(): void
    {
        $domainMessage = new ProcessDomain('domain-id');
        self::assertSame('domain-id', $domainMessage->domainId);

        $reportMessage = new ProcessReport('report-id');
        self::assertSame('report-id', $reportMessage->reportId);

        $emailMessage = new ProcessEmail('email-id');
        self::assertSame('email-id', $emailMessage->emailId);
    }
}
