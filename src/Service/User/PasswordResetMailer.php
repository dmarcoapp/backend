<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class PasswordResetMailer
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire(param: 'app.auth.user.reset_password.token.ttl_seconds')]
        private int $passwordResetTokenLifetime,
    ) {}

    public function send(User $user): void
    {
        $emailAddress = $user->getEmail();
        if (null === $emailAddress || '' === trim($emailAddress)) {
            return;
        }

        $token = $user->getPasswordResetToken();
        if (null === $token) {
            return;
        }

        $tokenExpiresAt = $user->getPasswordResetTokenExpiresAt();
        if (null === $tokenExpiresAt) {
            return;
        }

        $email = new TemplatedEmail()
            ->to(new Address($emailAddress))
            ->subject('Password reset request')
            ->htmlTemplate('emails/user/password-reset.html.twig')
            ->context([
                'name' => $user->getName(),
                'tokenEmail' => $user->getEmail(),
                'token' => $token,
                'tokenLifetimeHours' => intdiv($this->passwordResetTokenLifetime, 3600),
            ])
        ;

        $this->mailer->send($email);
    }
}
