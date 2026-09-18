<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class LoginNotificationMailer
{
    public function __construct(
        private MailerInterface $mailer,
    ) {}

    public function send(User $user, ?string $ip, ?string $userAgent, \DateTimeInterface $occurredAt): void
    {
        if (!$user->isUnusualNewLoginNotificationEnabled()) {
            return;
        }

        $emailAddress = $user->getEmail();
        if (null === $emailAddress || '' === trim($emailAddress)) {
            return;
        }

        $email = new TemplatedEmail()
            ->to(new Address($emailAddress))
            ->subject('New sign-in to your account')
            ->htmlTemplate('emails/user/login-notification.html.twig')
            ->context([
                'name' => $user->getName(),
                'ip' => $ip,
                'userAgent' => $userAgent,
                'occurredAt' => $occurredAt,
            ])
        ;

        $this->mailer->send($email);
    }
}
