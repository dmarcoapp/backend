<?php

declare(strict_types=1);

namespace App\Tests\Service;

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
    public function testLookupRejectsInvalidIp(): void
    {
        $service = new RdapLookupService(new MockHttpClient());

        $this->expectException(\InvalidArgumentException::class);
        $service->lookup('not-an-ip');
    }

    public function testLookupReturnsEmptyWhenBootstrapHasNoMatch(): void
    {
        $responses = [
            new MockResponse(json_encode(['services' => []], JSON_THROW_ON_ERROR)),
        ];

        $client = new MockHttpClient($responses);
        $service = new RdapLookupService($client);

        $result = $service->lookup('1.2.3.4');

        self::assertSame(
            [
                'name' => null,
                'country' => null,
                'email' => [
                    'abuse' => null,
                    'tech' => null,
                ],
            ],
            $result
        );
    }

    public function testLookupParsesRdapResponse(): void
    {
        $bootstrap = [
            'services' => [
                [
                    ['192.0.2.0/24'],
                    ['https://rdap.example.test'],
                ],
            ],
        ];

        $rdapResponse = [
            'name' => 'ExampleNet',
            'country' => 'US',
            'entities' => [
                [
                    'roles' => ['abuse'],
                    'vcardArray' => [
                        'vcard',
                        [
                            ['email', [], 'text', 'abuse@example.test'],
                        ],
                    ],
                ],
                [
                    'roles' => ['technical'],
                    'vcardArray' => [
                        'vcard',
                        [
                            ['email', [], 'text', 'tech@example.test'],
                        ],
                    ],
                ],
            ],
        ];

        $responses = [
            new MockResponse(json_encode($bootstrap, JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode($rdapResponse, JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ];

        $client = new MockHttpClient($responses);
        $service = new RdapLookupService($client);

        $result = $service->lookup('192.0.2.5');

        self::assertSame('ExampleNet', $result['name']);
        self::assertSame('US', $result['country']);
        self::assertSame('abuse@example.test', $result['email']['abuse']);
        self::assertSame('tech@example.test', $result['email']['tech']);
    }

    public function testLookupReturnsEmptyWhenRdapResponseNotOk(): void
    {
        $bootstrap = [
            'services' => [
                [
                    ['203.0.113.0/24'],
                    ['https://rdap.example.test'],
                ],
            ],
        ];

        $responses = [
            new MockResponse(json_encode($bootstrap, JSON_THROW_ON_ERROR)),
            new MockResponse('[]', ['http_code' => 500]),
        ];

        $client = new MockHttpClient($responses);
        $service = new RdapLookupService($client);

        $result = $service->lookup('203.0.113.10');

        self::assertSame(
            [
                'name' => null,
                'country' => null,
                'email' => [
                    'abuse' => null,
                    'tech' => null,
                ],
            ],
            $result
        );
    }

    public function testLookupSkipsGenericNetworkNameAndUsesNestedEntities(): void
    {
        $bootstrap = [
            'services' => [
                [
                    ['198.18.0.0/15'],
                    ['https://rdap.example.test'],
                ],
            ],
        ];

        $rdapResponse = [
            'name' => 'GLOBAL ISP NETWORK',
            'country' => 'US',
            'entities' => [
                [
                    'roles' => ['abuse'],
                    'vcardArray' => [
                        'vcard',
                        [
                            ['email', [], 'text', ['abuse@example.test']],
                        ],
                    ],
                    'entities' => [
                        [
                            'roles' => ['technical'],
                            'vcardArray' => [
                                'vcard',
                                [
                                    ['email', [], 'text', 'tech@example.test'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $responses = [
            new MockResponse(json_encode($bootstrap, JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode($rdapResponse, JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ];

        $client = new MockHttpClient($responses);
        $service = new RdapLookupService($client);

        $result = $service->lookup('198.18.0.1');

        self::assertNull($result['name']);
        self::assertSame('US', $result['country']);
        self::assertSame('abuse@example.test', $result['email']['abuse']);
        self::assertSame('tech@example.test', $result['email']['tech']);
    }
}
