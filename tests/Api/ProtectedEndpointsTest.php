<?php

declare(strict_types=1);

namespace App\Tests\Api;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 *
 * @coversNothing
 */
final class ProtectedEndpointsTest extends ApiTestCase
{
    #[DataProvider('protectedEndpointsProvider')]
    public function testProtectedEndpointsRequireAuthentication(string $method, string $uri, ?array $payload = null): void
    {
        $response = $this->requestJson(
            method: $method,
            uri: $uri,
            payload: $payload,
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public static function protectedEndpointsProvider(): iterable
    {
        yield ['GET', '/v1/user/profile'];

        yield ['PATCH', '/v1/user/profile', ['name' => 'Example']];

        yield ['GET', '/v1/user/profile/2fa'];

        yield ['POST', '/v1/user/profile/2fa/app/enable', ['code' => '000000']];

        yield ['POST', '/v1/user/profile/2fa/app/disable'];

        yield ['GET', '/v1/user/notifications'];

        yield ['PATCH', '/v1/user/notifications', ['unusualNewLoginNotificationEnabled' => false]];

        yield ['GET', '/v1/user/blocklist'];

        yield ['POST', '/v1/user/blocklist', ['pattern' => '*@example.com']];

        yield ['DELETE', '/v1/user/blocklist', ['ids' => ['00000000-0000-0000-0000-000000000000']]];

        yield ['DELETE', '/v1/user/blocklist/00000000-0000-0000-0000-000000000000'];

        yield ['DELETE', '/v1/user/profile', ['email' => 'user@example.com']];

        yield ['GET', '/v1/user/dashboard'];

        yield ['GET', '/v1/dmarc/domains'];

        yield ['GET', '/v1/dmarc/domains/00000000-0000-0000-0000-000000000000'];

        yield ['GET', '/v1/dmarc/reports'];

        yield ['DELETE', '/v1/dmarc/reports', ['ids' => ['00000000-0000-0000-0000-000000000000']]];

        yield ['GET', '/v1/dmarc/reports/00000000-0000-0000-0000-000000000000'];

        yield ['DELETE', '/v1/dmarc/reports/00000000-0000-0000-0000-000000000000'];

        yield ['GET', '/v1/dmarc/reports/00000000-0000-0000-0000-000000000000/xml'];

        yield ['GET', '/v1/dmarc/reports/00000000-0000-0000-0000-000000000000/records'];

        yield ['POST', '/v1/user/logout', ['refresh_token' => 'dummy']];
    }
}
