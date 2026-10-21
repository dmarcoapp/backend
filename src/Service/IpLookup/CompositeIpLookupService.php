<?php

declare(strict_types=1);

namespace App\Service\IpLookup;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class CompositeIpLookupService implements IpLookupInterface
{
    private const int CACHE_TTL = 4838400; // 8 weeks

    public function __construct(
        private IpWhoisLookupService $ipWhoisLookup,
        private RdapLookupService $rdapLookup,
        private CacheInterface $ipLookupCachePool,
        #[Autowire(env: 'bool:APP_IP_LOOKUP_ENABLED')]
        private bool $enabled,
    ) {}

    #[\Override]
    public function lookup(string $ip): array
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException('Invalid IP address');
        }

        // A lookup sends the address to ipwho.is and to the regional internet
        // registry that holds it. Switched off, nothing leaves the server and
        // records keep the bare address.
        if (!$this->enabled) {
            return [
                'name' => null,
                'country' => null,
                'email' => [
                    'abuse' => null,
                    'tech' => null,
                ],
            ];
        }

        $cacheKey = 'ip_lookup_composite_'.md5($ip);

        return $this->ipLookupCachePool->get($cacheKey, function (ItemInterface $item) use ($ip) {
            $item->expiresAfter(self::CACHE_TTL);

            $whois = $this->ipWhoisLookup->lookup($ip);
            $rdap = $this->rdapLookup->lookup($ip);

            return [
                'name' => $whois['name'] ?? null,
                'country' => $whois['country'] ?? null,
                'email' => [
                    'abuse' => $rdap['email']['abuse'] ?? null,
                    'tech' => $rdap['email']['tech'] ?? null,
                ],
            ];
        });
    }
}
