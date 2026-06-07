<?php

declare(strict_types=1);

namespace App\Tests\Service\IpLookup;

use App\Service\IpLookup\CompositeIpLookupService;
use App\Service\IpLookup\IpWhoisLookupService;
use App\Service\IpLookup\RdapLookupService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 */
#[CoversClass(CompositeIpLookupService::class)]
final class CompositeIpLookupServiceTest extends TestCase
{
    public function testLookupThrowsOnInvalidIp(): void
    {
        $service = new CompositeIpLookupService(
            new IpWhoisLookupService(new MockHttpClient()),
            new RdapLookupService(new MockHttpClient()),
            new ArrayAdapter(),
        );

        $this->expectException(\InvalidArgumentException::class);

        $service->lookup('invalid-ip');
    }

    public function testLookupCombinesResults(): void
    {
        $ipWhoisPayload = [
            'success' => true,
            'country' => 'United States',
            'connection' => [
                'org' => 'Google LLC',
            ],
        ];

        $rdapBootstrap = [
            'services' => [
                [
                    ['192.0.2.0/24'],
                    ['https://rdap.example'],
                ],
            ],
        ];

        $rdapResponse = [
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
                        ['email', [], [], 'tech@example.com'],
                    ]],
                ],
            ],
        ];

        $ipWhoisClient = new MockHttpClient([
            new MockResponse(json_encode($ipWhoisPayload, JSON_THROW_ON_ERROR)),
        ]);

        $rdapClient = new MockHttpClient([
            new MockResponse(json_encode($rdapBootstrap, JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode($rdapResponse, JSON_THROW_ON_ERROR)),
        ]);

        $service = new CompositeIpLookupService(
            new IpWhoisLookupService($ipWhoisClient),
            new RdapLookupService($rdapClient),
            new ArrayAdapter(),
        );

        $result = $service->lookup('192.0.2.1');

        self::assertSame('Google LLC', $result['name']);
        self::assertSame('United States', $result['country']);
        self::assertSame('abuse@example.com', $result['email']['abuse']);
        self::assertSame('tech@example.com', $result['email']['tech']);
        self::assertSame(1, $ipWhoisClient->getRequestsCount());
        self::assertSame(2, $rdapClient->getRequestsCount());
    }

    public function testLookupCachesCombinedResult(): void
    {
        $ipWhoisPayload = [
            'success' => true,
            'country' => 'United States',
            'connection' => [
                'isp' => 'Google LLC',
            ],
        ];

        $rdapBootstrap = [
            'services' => [
                [
                    ['198.51.100.0/24'],
                    ['https://rdap.example'],
                ],
            ],
        ];

        $rdapResponse = [
            'name' => 'ExampleNet',
            'country' => 'HU',
            'entities' => [
                [
                    'roles' => ['abuse'],
                    'vcardArray' => [null, [
                        ['email', [], [], 'abuse@example.com'],
                    ]],
                ],
            ],
        ];

        $ipWhoisClient = new MockHttpClient([
            new MockResponse(json_encode($ipWhoisPayload, JSON_THROW_ON_ERROR)),
        ]);

        $rdapClient = new MockHttpClient([
            new MockResponse(json_encode($rdapBootstrap, JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode($rdapResponse, JSON_THROW_ON_ERROR)),
        ]);

        $cache = new ArrayAdapter();
        $service = new CompositeIpLookupService(
            new IpWhoisLookupService($ipWhoisClient),
            new RdapLookupService($rdapClient),
            $cache,
        );

        $first = $service->lookup('198.51.100.10');
        $second = $service->lookup('198.51.100.10');

        self::assertSame($first, $second);
        self::assertSame(1, $ipWhoisClient->getRequestsCount());
        self::assertSame(2, $rdapClient->getRequestsCount());
    }
}
