<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class VerificationMailer
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire(param: 'app.auth.user.email_verification.token.ttl_seconds')]
        private int $emailVerificationTokenLifetime,
    ) {}

    public function send(User $user): void
    {
        $emailAddress = $user->getEmail();
        if (null === $emailAddress || '' === trim($emailAddress)) {
            return;
        }

        $token = $user->getEmailVerificationToken();
        if (null === $token) {
            return;
        }

        $tokenExpiresAt = $user->getEmailVerificationTokenExpiresAt();
        if (null === $tokenExpiresAt) {
            return;
        }

        $email = new TemplatedEmail()
            ->to(new Address($emailAddress))
            ->subject('Verify your email address!')
            ->htmlTemplate('emails/user/email-verification.html.twig')
            ->context([
                'name' => $user->getName(),
                'tokenEmail' => $user->getEmail(),
                'token' => $token,
                'tokenLifetimeHours' => intdiv($this->emailVerificationTokenLifetime, 3600),
            ])
        ;

        $this->mailer->send($email);
    }
}
