<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\RefreshToken;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Ends every session of an account once its password changes. Access tokens
 * carry the account's session version, so raising it refuses them (see
 * SessionVersionClaim). Refresh tokens would mint new ones, so they are
 * deleted here.
 */
final readonly class SessionRevoker
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JWTTokenManagerInterface $jwtTokenManager,
        private RefreshTokenGeneratorInterface $refreshTokenGenerator,
        private RefreshTokenManagerInterface $refreshTokenManager,
        #[Autowire(param: 'gesdinet_jwt_refresh_token.ttl')]
        private int $refreshTokenTtl,
    ) {}

    public function revokeAll(User $user): void
    {
        $user->endAllSessions();
        $this->entityManager->flush();

        $metadata = $this->entityManager->getClassMetadata(RefreshToken::class);
        $this->entityManager->getConnection()->delete(
            $metadata->getTableName(),
            [$metadata->getColumnName('username') => $user->getUserIdentifier()],
        );
    }

    /**
     * A fresh session for the one that changed the password, so that it is
     * not signed out with the rest. Issue it after the new password is saved.
     *
     * @return array{token: string, refresh_token: string, refresh_token_expiration: int}
     */
    public function issue(User $user): array
    {
        $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl($user, $this->refreshTokenTtl);
        $this->refreshTokenManager->save($refreshToken);

        return [
            'token' => $this->jwtTokenManager->create($user),
            'refresh_token' => (string) $refreshToken->getRefreshToken(),
            'refresh_token_expiration' => $refreshToken->getValid()?->getTimestamp() ?? 0,
        ];
    }
}
