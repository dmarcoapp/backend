<?php

declare(strict_types=1);

namespace App\Service\IpLookup;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class IpWhoisLookupService implements IpLookupInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {}

    #[\Override]
    public function lookup(string $ip): array
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException('Invalid IP address');
        }

        $response = $this->httpClient->request(
            'GET',
            'http://ipwho.is/'.rawurlencode($ip),
            ['timeout' => 5]
        );

        if (200 !== $response->getStatusCode()) {
            return $this->emptyResult();
        }

        $data = $response->toArray(throw: false);

        if (!is_array($data) || ($data['success'] ?? null) !== true) {
            return $this->emptyResult();
        }

        $country = null;
        if (!empty($data['country']) && is_string($data['country'])) {
            $country = trim($data['country']);
        }

        $name = null;
        $connection = $data['connection'] ?? null;
        if (is_array($connection) && !empty($connection['org']) && is_string($connection['org'])) {
            $name = trim($connection['org']);
        }

        return [
            'name' => $name ?: null,
            'country' => $country ?: null,
            'email' => [
                'abuse' => null,
                'tech' => null,
            ],
        ];
    }

    private function emptyResult(): array
    {
        return [
            'name' => null,
            'country' => null,
            'email' => [
                'abuse' => null,
                'tech' => null,
            ],
        ];
    }
}
