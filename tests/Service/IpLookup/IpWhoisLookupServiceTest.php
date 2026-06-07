<?php

declare(strict_types=1);

namespace App\Tests\Service\IpLookup;

use App\Service\IpLookup\IpWhoisLookupService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 */
#[CoversClass(IpWhoisLookupService::class)]
final class IpWhoisLookupServiceTest extends TestCase
{
    public function testLookupThrowsOnInvalidIp(): void
    {
        $service = new IpWhoisLookupService(new MockHttpClient());

        $this->expectException(\InvalidArgumentException::class);

        $service->lookup('invalid-ip');
    }

    public function testLookupReturnsEmptyWhenResponseNotOk(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('oops', ['http_code' => 500]),
        ]);

        $service = new IpWhoisLookupService($httpClient);
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

    public function testLookupReturnsEmptyWhenSuccessFalse(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['success' => false], JSON_THROW_ON_ERROR)),
        ]);

        $service = new IpWhoisLookupService($httpClient);
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

    public function testLookupParsesNameAndCountry(): void
    {
        $payload = [
            'success' => true,
            'country' => 'United States',
            'connection' => [
                'org' => 'Google LLC',
            ],
        ];

        $httpClient = new MockHttpClient([
            new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        $service = new IpWhoisLookupService($httpClient);
        $result = $service->lookup('8.8.4.4');

        self::assertSame('Google LLC', $result['name']);
        self::assertSame('United States', $result['country']);
        self::assertNull($result['email']['abuse']);
        self::assertNull($result['email']['tech']);
    }
}
