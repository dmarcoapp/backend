<?php

declare(strict_types=1);

namespace App\Tests\Service\Intake;

use App\Service\Intake\ReportArchiveException;
use App\Service\Intake\ReportArchiveExtractor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ReportArchiveExtractor::class)]
final class ReportArchiveExtractorTest extends TestCase
{
    private const string XML = '<?xml version="1.0"?><feedback><report_metadata><org_name>example</org_name></report_metadata></feedback>';

    public function testPassesPlainXmlThrough(): void
    {
        self::assertSame(self::XML, new ReportArchiveExtractor()->extract(self::XML));
        self::assertSame("\xEF\xBB\xBF".self::XML, new ReportArchiveExtractor()->extract("\xEF\xBB\xBF".self::XML));
    }

    public function testUnpacksGzip(): void
    {
        self::assertSame(self::XML, new ReportArchiveExtractor()->extract((string) gzencode(self::XML)));
    }

    public function testUnpacksZip(): void
    {
        $zip = self::zip(['google.com!example.com!1700000000!1700086400.xml' => self::XML]);

        self::assertSame(self::XML, new ReportArchiveExtractor()->extract($zip));
    }

    public function testToleratesADirectoryEntryInAZip(): void
    {
        $zip = self::zip(['reports/' => null, 'reports/report.xml' => self::XML]);

        self::assertSame(self::XML, new ReportArchiveExtractor()->extract($zip));
    }

    public function testTheKindOfFileComesFromItsBytesNotItsName(): void
    {
        // A gzip file is unpacked as gzip whatever it was called, and the
        // caller never passes a name to be fooled by.
        self::assertSame(self::XML, new ReportArchiveExtractor()->extract((string) gzencode(self::XML)));

        $this->expectException(ReportArchiveException::class);
        new ReportArchiveExtractor()->extract("%PDF-1.7\n".self::XML);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedProvider(): iterable
    {
        $bigXml = '<feedback>'.str_repeat(' ', ReportArchiveExtractor::MAX_XML_BYTES).'</feedback>';

        yield 'empty' => ['', 'empty'];
        yield 'neither XML nor an archive' => ["%PDF-1.7\nnot a report", 'neither XML'];
        yield 'too large before unpacking' => [str_repeat('<', ReportArchiveExtractor::MAX_INPUT_BYTES + 1), 'size limit'];
        yield 'plain XML past the limit' => [$bigXml, 'size limit'];
        yield 'gzip bomb past the limit' => [(string) gzencode($bigXml, 9), 'size limit'];
        yield 'gzip that unpacks far past its ratio' => [(string) gzencode('<feedback>'.str_repeat('a', 2 * 1024 * 1024).'</feedback>', 9), 'size limit'];
        yield 'truncated gzip' => [substr((string) gzencode(self::XML), 0, -12), 'truncated'];
        yield 'gzip with junk after it' => [gzencode(self::XML).'junk', 'goes on after'];
        yield 'two gzip members' => [gzencode(self::XML).gzencode(self::XML), 'goes on after'];
        yield 'gzip holding no XML' => [(string) gzencode('MZ binary'), 'other than XML'];
        yield 'damaged gzip' => ["\x1f\x8b\x08\x00garbage-that-is-not-deflate", 'damaged'];
        yield 'zip with two files' => [self::zip(['a.xml' => self::XML, 'b.xml' => self::XML]), 'more than one'];
        yield 'zip with a path outside itself' => [self::zip(['../../etc/report.xml' => self::XML]), 'outside itself'];
        yield 'zip with an absolute path' => [self::zip(['/tmp/report.xml' => self::XML]), 'outside itself'];
        yield 'zip holding something else' => [self::zip(['report.exe' => self::XML]), 'other than an XML report'];
        yield 'zip holding a zip' => [self::zip(['report.xml' => self::zip(['inner.xml' => self::XML])]), 'other than XML'];
        yield 'zip with many entries' => [self::zip(array_fill_keys(array_map(static fn (int $i): string => "d$i/", range(1, 6)), null)), 'one report'];
        yield 'zip with only directories' => [self::zip(['reports/' => null]), 'no report'];
        yield 'zip bomb' => [self::zip(['report.xml' => $bigXml]), 'size limit'];
        yield 'damaged zip' => ["PK\x03\x04".str_repeat("\0", 64), 'damaged'];
    }

    #[DataProvider('refusedProvider')]
    public function testRefuses(string $content, string $reason): void
    {
        $this->expectException(ReportArchiveException::class);
        $this->expectExceptionMessage($reason);

        new ReportArchiveExtractor()->extract($content);
    }

    public function testRefusesAnEncryptedZip(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'dmarco-test-');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('report.xml', self::XML);
        $zip->setEncryptionName('report.xml', \ZipArchive::EM_AES_256, 'secret');
        $zip->close();
        $content = (string) file_get_contents($path);
        unlink($path);

        $this->expectException(ReportArchiveException::class);
        $this->expectExceptionMessage('encrypted');

        new ReportArchiveExtractor()->extract($content);
    }

    public function testLeavesNoTemporaryFileBehind(): void
    {
        $before = glob(sys_get_temp_dir().'/dmarco-report-*') ?: [];

        new ReportArchiveExtractor()->extract(self::zip(['report.xml' => self::XML]));

        try {
            new ReportArchiveExtractor()->extract(self::zip(['a.xml' => self::XML, 'b.xml' => self::XML]));
        } catch (ReportArchiveException) {
        }

        self::assertSame($before, glob(sys_get_temp_dir().'/dmarco-report-*') ?: []);
    }

    /**
     * @return iterable<string, array{?string, ?string, bool}>
     */
    public static function candidateProvider(): iterable
    {
        yield 'zip by name' => ['google.com!example.com!1!2.zip', 'application/octet-stream', true];
        yield 'gzip by name' => ['report.xml.gz', null, true];
        yield 'xml by name' => ['Report.XML', null, true];
        yield 'gzip by type' => [null, 'application/gzip; name="report"', true];
        yield 'zip by type' => ['', 'application/zip', true];
        yield 'xml by type' => [null, 'text/xml', true];
        yield 'image' => ['logo.png', 'image/png', false];
        yield 'nothing known' => [null, null, false];
    }

    #[DataProvider('candidateProvider')]
    public function testTellsAReportAttachmentFromOthers(?string $filename, ?string $contentType, bool $candidate): void
    {
        self::assertSame($candidate, ReportArchiveExtractor::isReportCandidate($filename, $contentType));
    }

    /**
     * @param array<string, ?string> $entries null makes a directory
     */
    private static function zip(array $entries): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'dmarco-test-');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            null === $content ? $zip->addEmptyDir(rtrim($name, '/')) : $zip->addFromString($name, $content);
        }
        $zip->close();
        $content = (string) file_get_contents($path);
        unlink($path);

        return $content;
    }
}
