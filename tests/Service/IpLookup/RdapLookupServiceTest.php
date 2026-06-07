<?php

declare(strict_types=1);

namespace App\Tests\Service\IpLookup;

use App\Service\IpLookup\RdapLookupService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 *
 * @coversNothing
 */
final class RdapLookupServiceTest extends TestCase
{
    public function testLookupThrowsOnInvalidIp(): void
    {
        $service = new RdapLookupService(new MockHttpClient());

        $this->expectException(\InvalidArgumentException::class);

        $service->lookup('invalid-ip');
    }

    public function testLookupReturnsEmptyWhenNoServiceMatches(): void
    {
        $bootstrap = ['services' => [[['198.51.100.0/24'], ['https://rdap.example']]]];
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode($bootstrap)),
        ]);

        $service = new RdapLookupService($httpClient);
        $result = $service->lookup('192.0.2.1');

        self::assertSame([
            'name' => null,
            'country' => null,
            'email' => [
                'abuse' => null,
                'tech' => null,
            ],
        ], $result);
    }

    public function testLookupReturnsEmptyWhenRdapRequestFails(): void
    {
        $bootstrap = ['services' => [[['192.0.2.0/24'], ['https://rdap.example']]]];
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode($bootstrap)),
            new MockResponse('oops', ['http_code' => 500]),
        ]);

        $service = new RdapLookupService($httpClient);
        $result = $service->lookup('192.0.2.1');

        self::assertSame([
            'name' => null,
            'country' => null,
            'email' => [
                'abuse' => null,
                'tech' => null,
            ],
        ], $result);
    }

    public function testLookupParsesRdapResponse(): void
    {
        $bootstrap = ['services' => [[['192.0.2.0/24'], ['https://rdap.example']]]];
        $rdap = [
            'name' => 'ExampleNet',
            'country' => 'HU',
            'entities' => [
                [
                    'roles' => ['abuse'],
                    'vcardArray' => [null, [
                        ['email', [], [], 'abuse@example.com'],
                    ]],
                ],
                [
                    'roles' => ['technical'],
                    'vcardArray' => [null, [
                        ['email', [], [], ['tech@example.com']],
                    ]],
                    'entities' => [
                        [
                            'roles' => ['abuse'],
                            'vcardArray' => [null, [
                                ['email', [], [], 'nested-abuse@example.com'],
                            ]],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = new MockHttpClient([
            new MockResponse(json_encode($bootstrap)),
            new MockResponse(json_encode($rdap)),
        ]);

        $service = new RdapLookupService($httpClient);
        $result = $service->lookup('192.0.2.1');

        self::assertSame('ExampleNet', $result['name']);
        self::assertSame('HU', $result['country']);
        self::assertSame('abuse@example.com', $result['email']['abuse']);
        self::assertSame('tech@example.com', $result['email']['tech']);
    }

    public function testLookupSkipsGenericNetworkName(): void
    {
        $bootstrap = ['services' => [[['192.0.2.0/24'], ['https://rdap.example']]]];
        $rdap = [
            'name' => 'WORLD ISP NETWORK',
            'country' => 'US',
            'entities' => [],
        ];

        $httpClient = new MockHttpClient([
            new MockResponse(json_encode($bootstrap)),
            new MockResponse(json_encode($rdap)),
        ]);

        $service = new RdapLookupService($httpClient);
        $result = $service->lookup('192.0.2.1');

        self::assertNull($result['name']);
        self::assertSame('US', $result['country']);
    }
}
