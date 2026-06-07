<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class PasswordChangedMailer
{
    public function __construct(
        private MailerInterface $mailer,
    ) {}

    public function send(User $user): void
    {
        $emailAddress = $user->getEmail();
        if (null === $emailAddress || '' === trim($emailAddress)) {
            return;
        }

        $email = new TemplatedEmail()
            ->to(new Address($emailAddress))
            ->subject('Password has been successfully changed')
            ->htmlTemplate('emails/user/password-change.html.twig')
            ->context([
                'name' => $user->getName(),
            ])
        ;

        $this->mailer->send($email);
    }
}
