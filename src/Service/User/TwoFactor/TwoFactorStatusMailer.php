<?php

declare(strict_types=1);

namespace App\Service\User\TwoFactor;

use App\Entity\User\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class TwoFactorStatusMailer
{
    public function __construct(
        private MailerInterface $mailer,
    ) {}

    public function sendEnabled(User $user): void
    {
        $email = new TemplatedEmail()
            ->to(new Address($user->getEmail() ?? ''))
            ->subject('Authenticator app turned on for DMARCo')
            ->htmlTemplate('emails/user/two-factor-enabled.html.twig')
            ->textTemplate('emails/user/two-factor-enabled.txt.twig')
            ->context([
                'name' => $user->getName(),
            ])
        ;

        $this->mailer->send($email);
    }

    public function sendDisabled(User $user): void
    {
        $email = new TemplatedEmail()
            ->to(new Address($user->getEmail() ?? ''))
            ->subject('Authenticator app turned off for DMARCo')
            ->htmlTemplate('emails/user/two-factor-disabled.html.twig')
            ->textTemplate('emails/user/two-factor-disabled.txt.twig')
            ->context([
                'name' => $user->getName(),
            ])
        ;

        $this->mailer->send($email);
    }
}
