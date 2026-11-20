<?php

declare(strict_types=1);

namespace App\Repository\Platform;

use App\Entity\Platform\PlatformSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\String\ByteString;

/**
 * @extends ServiceEntityRepository<PlatformSettings>
 */
class PlatformSettingsRepository extends ServiceEntityRepository
{
    public const string TOKEN_ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatformSettings::class);
    }

    /**
     * The one row of settings. The migration that introduced it creates it,
     * and an empty database, such as a test's, gets it on first use.
     */
    public function get(): PlatformSettings
    {
        $settings = $this->find(PlatformSettings::ID);
        if ($settings instanceof PlatformSettings) {
            return $settings;
        }

        $settings = new PlatformSettings(self::generateReportAddressToken());
        $this->getEntityManager()->persist($settings);
        $this->getEntityManager()->flush();

        return $settings;
    }

    public function save(PlatformSettings $settings): void
    {
        $this->getEntityManager()->persist($settings);
        $this->getEntityManager()->flush();
    }

    public static function generateReportAddressToken(): string
    {
        return ByteString::fromRandom(32, self::TOKEN_ALPHABET)->toString();
    }
}
