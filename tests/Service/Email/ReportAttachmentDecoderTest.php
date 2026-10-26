<?php

declare(strict_types=1);

namespace App\Tests\Service\Email;

use App\Service\Email\ReportAttachmentDecoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ReportAttachmentDecoder::class)]
final class ReportAttachmentDecoderTest extends TestCase
{
    public function testDecodesGzipBase64(): void
    {
        $xml = '<?xml version="1.0"?><feedback/>';

        self::assertSame($xml, new ReportAttachmentDecoder()->decode(base64_encode((string) gzencode($xml))));
    }

    public function testDecodesAReportAcrossManySteps(): void
    {
        // Random, so it hardly compresses and spans many decompression steps.
        $xml = '<?xml version="1.0"?><feedback>'.bin2hex(random_bytes(200_000)).'</feedback>';

        self::assertSame($xml, new ReportAttachmentDecoder()->decode(base64_encode((string) gzencode($xml))));
    }

    public function testAcceptsAReportExactlyAtTheLimit(): void
    {
        $xml = str_repeat('a', ReportAttachmentDecoder::MAX_XML_BYTES);

        self::assertSame(
            ReportAttachmentDecoder::MAX_XML_BYTES,
            strlen(new ReportAttachmentDecoder()->decode(base64_encode((string) gzencode($xml)))),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unreadable(): iterable
    {
        yield 'not base64' => ['not base64!', 'not base64'];
        yield 'empty' => ['', 'not base64'];
        yield 'not gzip' => [base64_encode('<feedback/>'), 'not gzip'];
        yield 'truncated' => [base64_encode(substr((string) gzencode(str_repeat('report ', 1000)), 0, 20)), 'truncated'];
        yield 'data after the stream' => [base64_encode(gzencode('<feedback/>').'JUNK'), 'goes on after'];
        // Long enough that the second member starts in a later step.
        yield 'a second member' => [base64_encode(gzencode(random_bytes(6000)).gzencode('<feedback/>')), 'goes on after'];
        yield 'over the limit' => [base64_encode((string) gzencode(str_repeat('a', ReportAttachmentDecoder::MAX_XML_BYTES + 1))), 'larger than'];
    }

    #[DataProvider('unreadable')]
    public function testRefusesWhatItCannotRead(string $content, string $reason): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($reason);

        new ReportAttachmentDecoder()->decode($content);
    }
}
