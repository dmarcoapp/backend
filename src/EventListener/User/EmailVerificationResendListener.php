<?php

declare(strict_types=1);

namespace App\EventListener\User;

use App\Event\User\EmailVerificationResendEvent;
use App\Service\User\VerificationMailer;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class EmailVerificationResendListener
{
    public function __construct(
        private VerificationMailer $verificationMailer,
    ) {}

    public function __invoke(EmailVerificationResendEvent $event): void
    {
        $user = $event->getUser();
        $tokenExpiresAt = $user->getEmailVerificationTokenExpiresAt();

        if (empty($user->getEmailVerificationToken())) {
            return;
        }

        if (
            null === $tokenExpiresAt
            || $tokenExpiresAt->getTimestamp() <= new \DateTimeImmutable()->getTimestamp()
        ) {
            return;
        }

        $this->verificationMailer->send($user);
    }
}
