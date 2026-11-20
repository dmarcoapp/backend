<?php

declare(strict_types=1);

namespace App\Service\Intake;

use App\Entity\Intake\ApiIntakeKey;
use App\Repository\Intake\ApiIntakeKeyRepository;
use Symfony\Component\String\ByteString;

/**
 * Makes the keys that upload reports, and checks the one a request brings.
 * A key reads dmarco_<lookup id>_<secret>. Only the lookup id and a hash of
 * the secret are stored.
 */
final readonly class ApiIntakeKeys
{
    public const string PREFIX = 'dmarco_';

    private const int LOOKUP_ID_LENGTH = 16;
    private const int SECRET_LENGTH = 40;
    private const string LOOKUP_ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

    // Recording every use would write on every upload, so a use is recorded
    // once a minute at most.
    private const string LAST_USED_GRANULARITY = '-1 minute';

    public function __construct(
        private ApiIntakeKeyRepository $repository,
    ) {}

    /**
     * @return array{ApiIntakeKey, string} the key, and the token to show once
     */
    public function create(string $name): array
    {
        $lookupId = ByteString::fromRandom(self::LOOKUP_ID_LENGTH, self::LOOKUP_ALPHABET)->toString();
        $secret = ByteString::fromRandom(self::SECRET_LENGTH)->toString();

        $key = new ApiIntakeKey($name, $lookupId, hash('sha256', $secret));
        $this->repository->save($key);

        return [$key, self::PREFIX.$lookupId.'_'.$secret];
    }

    /**
     * The key an Authorization header names, or null for anything else.
     */
    public function authenticate(?string $authorization): ?ApiIntakeKey
    {
        $pattern = sprintf('/^Bearer\s+%s([a-z0-9]{%d})_([A-Za-z0-9]{%d})$/D', self::PREFIX, self::LOOKUP_ID_LENGTH, self::SECRET_LENGTH);
        if (null === $authorization || 1 !== preg_match($pattern, trim($authorization), $matches)) {
            return null;
        }

        $key = $this->repository->findOneBy(['lookupId' => $matches[1]]);
        if (!$key instanceof ApiIntakeKey || !hash_equals($key->getSecretHash(), hash('sha256', $matches[2]))) {
            return null;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $lastUsedAt = $key->getLastUsedAt();
        if (null === $lastUsedAt || $lastUsedAt < $now->modify(self::LAST_USED_GRANULARITY)) {
            $key->setLastUsedAt($now);
            $this->repository->save($key);
        }

        return $key;
    }

    /**
     * What a key looks like without its secret, for telling keys apart.
     */
    public static function preview(ApiIntakeKey $key): string
    {
        return self::PREFIX.$key->getLookupId().'_…';
    }
}
