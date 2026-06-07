<?php

declare(strict_types=1);

namespace App\EventListener\User;

use App\Event\User\PasswordChangeEvent;
use App\Service\User\PasswordChangedMailer;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class PasswordChangeListener
{
    public function __construct(
        private PasswordChangedMailer $passwordChangedMailer,
    ) {}

    public function __invoke(PasswordChangeEvent $event): void
    {
        $user = $event->getUser();

        $this->passwordChangedMailer->send($user);
    }
}
