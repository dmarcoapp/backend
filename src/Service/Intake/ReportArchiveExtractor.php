<?php

declare(strict_types=1);

namespace App\Service\Intake;

/**
 * Unpacks a DMARC aggregate report from the file it arrives in: plain XML, gzip
 * or zip. Whatever comes from a mailbox or the API is treated as hostile, so
 * the kind of file is taken from its first bytes, never from its name or its
 * declared type, and every size is counted while unpacking rather than taken
 * from a header that could lie.
 */
final readonly class ReportArchiveExtractor
{
    /**
     * The largest report the backend accepts once unpacked, the same limit the
     * mail gateway applied.
     */
    public const int MAX_XML_BYTES = 8 * 1024 * 1024;

    /**
     * The largest file accepted before unpacking. A compressed report is a
     * fraction of its XML, so this leaves room for any real one.
     */
    public const int MAX_INPUT_BYTES = 10 * 1024 * 1024;

    /**
     * How many times larger than its compressed form a report may unpack to.
     * Reports compress well, but not past this, while a zip or gzip bomb does.
     */
    public const int MAX_COMPRESSION_RATIO = 60;

    /**
     * A report zip holds one file. A few directory entries are tolerated, a
     * long list of entries is not even looked at.
     */
    public const int MAX_ZIP_ENTRIES = 5;

    private const int CHUNK_BYTES = 8192;
    private const string GZIP_MAGIC = "\x1f\x8b";
    private const string ZIP_MAGIC = "PK\x03\x04";

    /**
     * @throws ReportArchiveException
     */
    public function extract(string $content): string
    {
        $length = strlen($content);
        if (0 === $length) {
            throw new ReportArchiveException('The attachment is empty.');
        }
        if ($length > self::MAX_INPUT_BYTES) {
            throw new ReportArchiveException('The attachment is larger than the size limit.');
        }

        return match (true) {
            str_starts_with($content, self::GZIP_MAGIC) => $this->gunzip($content),
            str_starts_with($content, self::ZIP_MAGIC) => $this->unzip($content),
            self::looksLikeXml($content) => $this->plain($content),
            default => throw new ReportArchiveException('The attachment is neither XML, gzip nor zip.'),
        };
    }

    /**
     * Whether a file name or declared type is one a report could arrive as,
     * which is how a report attachment is told from any other.
     */
    public static function isReportCandidate(?string $filename, ?string $contentType): bool
    {
        $name = strtolower(trim((string) $filename));
        foreach (['.xml', '.xml.gz', '.gz', '.gzip', '.zip'] as $extension) {
            if ('' !== $name && str_ends_with($name, $extension)) {
                return true;
            }
        }

        $type = strtolower(trim(explode(';', (string) $contentType)[0]));

        return in_array($type, [
            'application/gzip',
            'application/x-gzip',
            'application/zip',
            'application/x-zip-compressed',
            'application/xml',
            'text/xml',
        ], true);
    }

    private function plain(string $content): string
    {
        if (strlen($content) > self::MAX_XML_BYTES) {
            throw new ReportArchiveException('The report is larger than the size limit.');
        }

        return $content;
    }

    private function gunzip(string $content): string
    {
        $context = inflate_init(ZLIB_ENCODING_GZIP);
        if (false === $context) {
            throw new \LogicException('Could not start decompressing.');
        }

        $limit = $this->unpackedLimit(strlen($content));
        $xml = '';
        $length = strlen($content);
        for ($offset = 0; $offset < $length; $offset += self::CHUNK_BYTES) {
            // Past the end of the stream, inflate would quietly start over on
            // whatever follows, so a second member or junk is refused.
            if (ZLIB_STREAM_END === inflate_get_status($context)) {
                throw new ReportArchiveException('The gzip file goes on after the report.');
            }

            // inflate_add warns as well as returning false on data that is not gzip.
            $chunk = @inflate_add($context, substr($content, $offset, self::CHUNK_BYTES), ZLIB_SYNC_FLUSH);
            if (false === $chunk) {
                throw new ReportArchiveException('The gzip file is damaged.');
            }

            $xml .= $chunk;
            if (strlen($xml) > $limit) {
                throw new ReportArchiveException('The gzip file unpacks past the size limit.');
            }
        }

        if (ZLIB_STREAM_END !== inflate_get_status($context)) {
            throw new ReportArchiveException('The gzip file is truncated.');
        }
        if (inflate_get_read_len($context) !== $length) {
            throw new ReportArchiveException('The gzip file goes on after the report.');
        }

        return $this->xmlOrFail($xml);
    }

    private function unzip(string $content): string
    {
        // ZipArchive reads from a file only. The file is the process's own,
        // readable by nobody else, and gone when this returns.
        $path = tempnam(sys_get_temp_dir(), 'dmarco-report-');
        if (false === $path) {
            throw new \RuntimeException('Could not create a temporary file.');
        }

        try {
            chmod($path, 0600);
            if (false === file_put_contents($path, $content)) {
                throw new \RuntimeException('Could not write a temporary file.');
            }

            return $this->unzipFile($path, strlen($content));
        } finally {
            @unlink($path);
        }
    }

    private function unzipFile(string $path, int $compressedLength): string
    {
        $zip = new \ZipArchive();
        if (true !== $zip->open($path, \ZipArchive::RDONLY | \ZipArchive::CHECKCONS)) {
            throw new ReportArchiveException('The zip file is damaged.');
        }

        try {
            if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ZIP_ENTRIES) {
                throw new ReportArchiveException('The zip file does not hold one report.');
            }

            $reportIndex = null;
            for ($index = 0; $index < $zip->numFiles; ++$index) {
                $entry = $zip->statIndex($index);
                if (false === $entry) {
                    throw new ReportArchiveException('The zip file is damaged.');
                }

                $name = $entry['name'];
                if (str_ends_with($name, '/')) {
                    continue;
                }

                // Nothing is written to disk by its name, but a name like these
                // marks an archive made to attack, not a report.
                if (str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")) {
                    throw new ReportArchiveException('The zip file names a path outside itself.');
                }

                if (null !== $reportIndex) {
                    throw new ReportArchiveException('The zip file holds more than one file.');
                }

                if (!str_ends_with(strtolower($name), '.xml')) {
                    throw new ReportArchiveException('The zip file holds something other than an XML report.');
                }

                if (0 !== ($entry['encryption_method'] ?? 0)) {
                    throw new ReportArchiveException('The zip file is encrypted.');
                }

                $reportIndex = $index;
            }

            if (null === $reportIndex) {
                throw new ReportArchiveException('The zip file holds no report.');
            }

            return $this->xmlOrFail($this->readEntry($zip, $reportIndex, $this->unpackedLimit($compressedLength)));
        } finally {
            $zip->close();
        }
    }

    /**
     * Reads one entry, counting what comes out, because the sizes in the zip
     * headers are whatever the archive claims.
     */
    private function readEntry(\ZipArchive $zip, int $index, int $limit): string
    {
        $stream = $zip->getStreamIndex($index);
        if (false === $stream) {
            throw new ReportArchiveException('The zip file is damaged.');
        }

        try {
            $xml = '';
            while (!feof($stream)) {
                $chunk = fread($stream, self::CHUNK_BYTES);
                if (false === $chunk) {
                    throw new ReportArchiveException('The zip file is damaged.');
                }
                $xml .= $chunk;
                if (strlen($xml) > $limit) {
                    throw new ReportArchiveException('The zip file unpacks past the size limit.');
                }
            }

            return $xml;
        } finally {
            fclose($stream);
        }
    }

    private function unpackedLimit(int $compressedLength): int
    {
        return min(self::MAX_XML_BYTES, $compressedLength * self::MAX_COMPRESSION_RATIO);
    }

    private function xmlOrFail(string $xml): string
    {
        if (!self::looksLikeXml($xml)) {
            throw new ReportArchiveException('The archive holds something other than XML.');
        }

        return $xml;
    }

    /**
     * A first, cheap look: the report schema check comes later and decides.
     */
    private static function looksLikeXml(string $content): bool
    {
        $start = ltrim(substr($content, 0, 512), "\xEF\xBB\xBF \t\r\n");

        return str_starts_with($start, '<');
    }
}
