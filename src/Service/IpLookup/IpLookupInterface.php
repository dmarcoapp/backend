<?php

declare(strict_types=1);

namespace App\Service\IpLookup;

interface IpLookupInterface
{
    /**
     * @return array{
     *     name: null|string,
     *     country: null|string,
     *     email: array{
     *         abuse: null|string,
     *         tech: null|string
     *     }
     * }
     */
    public function lookup(string $ip): array;
}
