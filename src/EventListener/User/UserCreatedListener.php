<?php

declare(strict_types=1);

namespace App\EventListener\User;

use App\Event\User\UserCreatedEvent;
use App\Service\User\VerificationMailer;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class UserCreatedListener
{
    public function __construct(
        private VerificationMailer $verificationMailer,
    ) {}

    public function __invoke(UserCreatedEvent $event): void
    {
        $user = $event->getUser();

        $this->verificationMailer->send($user);
    }
}
