<?php

declare(strict_types=1);

namespace App\Service\Email;

/**
 * The mail gateway sends each report inside the webhook, gzip-compressed and
 * base64-encoded. A report compresses to a fraction of its size, which keeps
 * the request well under PHP's 8 MB body limit even for the largest one the
 * gateway accepts.
 */
final readonly class ReportAttachmentDecoder
{
    public const int MAX_XML_BYTES = 8 * 1024 * 1024;

    // Small steps keep a single one from expanding past memory_limit before the
    // size check below gets to run.
    private const int CHUNK_BYTES = 4096;

    /**
     * @throws \InvalidArgumentException when the content is not a report this
     *                                   decoder can unpack within the limit
     */
    public function decode(string $content): string
    {
        $compressed = base64_decode($content, true);
        if (false === $compressed || '' === $compressed) {
            throw new \InvalidArgumentException('Attachment content is not base64.');
        }

        $context = inflate_init(ZLIB_ENCODING_GZIP);
        if (false === $context) {
            throw new \LogicException('Could not start decompressing.');
        }

        $xml = '';
        $length = strlen($compressed);
        for ($offset = 0; $offset < $length; $offset += self::CHUNK_BYTES) {
            // Past the end of the stream, inflate would quietly start over on
            // whatever follows, so a second member or plain junk is refused.
            if (ZLIB_STREAM_END === inflate_get_status($context)) {
                throw new \InvalidArgumentException('Attachment content goes on after the report.');
            }
            $xml .= $this->inflate($context, substr($compressed, $offset, self::CHUNK_BYTES));
            if (strlen($xml) > self::MAX_XML_BYTES) {
                throw new \InvalidArgumentException('Attachment is larger than the report size limit.');
            }
        }
        // A complete stream ends on its own. Asking it to finish afterwards would
        // only report that there was nothing left to do.
        if (ZLIB_STREAM_END !== inflate_get_status($context)) {
            throw new \InvalidArgumentException('Attachment content is truncated.');
        }
        // The rest of the step the stream ended in is dropped without a word.
        if (inflate_get_read_len($context) !== $length) {
            throw new \InvalidArgumentException('Attachment content goes on after the report.');
        }

        return $xml;
    }

    private function inflate(\InflateContext $context, string $data): string
    {
        // inflate_add warns as well as returning false on data that is not gzip.
        $output = @inflate_add($context, $data, ZLIB_SYNC_FLUSH);
        if (false === $output) {
            throw new \InvalidArgumentException('Attachment content is not gzip.');
        }

        return $output;
    }
}
