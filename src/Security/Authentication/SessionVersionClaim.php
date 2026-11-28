<?php

declare(strict_types=1);

namespace App\Security\Authentication;

use App\Entity\User\User;
use App\Repository\User\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Ties each access token to the account's session version. A token is
 * stateless and lives for an hour, so without this a session that changed
 * hands would outlive the password change meant to end it.
 *
 * A counter rather than anything derived from the password hash, because
 * the hash is also rewritten when its algorithm or cost is upgraded at
 * sign-in, right after the token is issued. A token without the claim, issued
 * before it existed, counts as version 0, so an upgrade signs nobody out.
 */
final readonly class SessionVersionClaim
{
    public const string CLAIM = 'sv';

    public function __construct(
        private UserRepository $userRepository,
    ) {}

    #[AsEventListener(event: Events::JWT_CREATED)]
    public function onJwtCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();
        $payload[self::CLAIM] = $user->getSessionVersion();
        $event->setData($payload);
    }

    #[AsEventListener(event: Events::JWT_DECODED)]
    public function onJwtDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        $identifier = $payload['username'] ?? null;
        if (!is_string($identifier)) {
            $event->markAsInvalid();

            return;
        }

        $user = $this->userRepository->findOneBy(['email' => $identifier]);
        if (!$user instanceof User) {
            // The user provider refuses the token in its turn.
            return;
        }

        $version = $payload[self::CLAIM] ?? 0;
        if (!is_int($version) || $version !== $user->getSessionVersion()) {
            $event->markAsInvalid();
        }
    }
}
