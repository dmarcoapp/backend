<?php

declare(strict_types=1);

namespace App\EventListener\User;

use App\Event\User\PasswordChangeEvent;
use App\Service\User\PasswordChangedMailer;
use App\Service\User\SessionRevoker;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class PasswordChangeListener
{
    public function __construct(
        private PasswordChangedMailer $passwordChangedMailer,
        private SessionRevoker $sessionRevoker,
    ) {}

    public function __invoke(PasswordChangeEvent $event): void
    {
        $user = $event->getUser();

        // Whoever else is signed in, perhaps the reason for the change, is
        // signed out with the old password.
        $this->sessionRevoker->revokeAll($user);
        $this->passwordChangedMailer->send($user);
    }
}
