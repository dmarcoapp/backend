<?php

declare(strict_types=1);

namespace App\Service\IpLookup;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class RdapLookupService implements IpLookupInterface
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

        $bootstrap = $this->httpClient->request(
            'GET',
            'https://data.iana.org/rdap/ipv4.json',
            ['timeout' => 5]
        )->toArray();

        $rdapBase = $this->resolveRdapBase($bootstrap, $ip);

        if (!$rdapBase) {
            return $this->emptyResult();
        }

        $response = $this->httpClient->request(
            'GET',
            rtrim($rdapBase, '/').'/ip/'.$ip,
            ['timeout' => 5]
        );

        if (200 !== $response->getStatusCode()) {
            return $this->emptyResult();
        }

        return $this->parseRdap($response->toArray(throw: false));
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

    private function resolveRdapBase(array $bootstrap, string $ip): ?string
    {
        foreach ($bootstrap['services'] ?? [] as $service) {
            [$ranges, $urls] = $service;

            foreach ($ranges as $cidr) {
                if ($this->ipInCidr($ip, $cidr)) {
                    return $urls[0] ?? null;
                }
            }
        }

        return null;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $ipBin = inet_pton($ip);
        $netBin = inet_pton($subnet);
        if (false === $ipBin || false === $netBin) {
            return false;
        }

        $bytes = intdiv((int) $bits, 8);
        $remainder = (int) $bits % 8;

        if (0 !== strncmp($ipBin, $netBin, $bytes)) {
            return false;
        }

        if (0 === $remainder) {
            return true;
        }

        $mask = ~(0xFF >> $remainder);

        return (ord($ipBin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask);
    }

    private function parseRdap(array $data): array
    {
        $result = $this->emptyResult();

        // ISP / NETWORK NAME
        if (!empty($data['name']) && is_string($data['name'])) {
            $name = trim($data['name']);

            if ('' !== $name && !$this->isGenericNetworkName($name)) {
                $result['name'] = $name;
            }
        }

        // Country
        if (!empty($data['country']) && is_string($data['country'])) {
            $result['country'] = trim($data['country']);
        }

        // Contacts
        foreach ($this->flattenEntities($data['entities'] ?? []) as $entity) {
            $roles = $entity['roles'] ?? [];

            if (in_array('abuse', $roles, true)) {
                $result['email']['abuse'] ??= $this->getVcard($entity, 'email');
            }

            if (in_array('technical', $roles, true)) {
                $result['email']['tech'] ??= $this->getVcard($entity, 'email');
            }
        }

        return $result;
    }

    private function isGenericNetworkName(string $name): bool
    {
        return (bool) preg_match(
            '/^(WORLD|GLOBAL|US|EU|ASIA)[-_ ]ISP[-_ ]NETWORK$/i',
            $name
        );
    }

    private function getVcard(array $entity, string $field): ?string
    {
        $vcard = $entity['vcardArray'][1] ?? null;

        if (!is_array($vcard)) {
            return null;
        }

        foreach ($vcard as $row) {
            if (!is_array($row) || ($row[0] ?? null) !== $field) {
                continue;
            }

            $value = $row[3] ?? null;

            if (is_string($value)) {
                return trim($value);
            }

            if (is_array($value) && isset($value[0]) && is_string($value[0])) {
                return trim($value[0]);
            }
        }

        return null;
    }

    private function flattenEntities(array $entities): array
    {
        $flat = [];

        foreach ($entities as $entity) {
            if (!is_array($entity)) {
                continue;
            }

            $flat[] = $entity;

            if (!empty($entity['entities']) && is_array($entity['entities'])) {
                array_push($flat, ...$this->flattenEntities($entity['entities']));
            }
        }

        return $flat;
    }
}
