<?php

declare(strict_types=1);

// The domain check reads DNS with dns_get_record(). Tests must not reach the
// network, so this stand-in in the handler's namespace answers instead, with
// whatever records a test puts in TestDnsRecordStore (none by default).
// Every test that reaches the domain check loads it with require_once.

namespace App\MessageHandler\DMARC;

final class TestDnsRecordStore
{
    /**
     * @var array<int, array<string, string>>
     */
    public static array $records = [];

    /**
     * @param array<int, array<string, string>> $records
     */
    public static function setRecords(array $records): void
    {
        self::$records = $records;
    }
}

function dns_get_record(string $hostname, int $type): array
{
    return TestDnsRecordStore::$records;
}
