<?php

declare(strict_types=1);

namespace App\Service\User\TwoFactor;

use App\Entity\User\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class TwoFactorCodeMailer
{
    public function __construct(
        private MailerInterface $mailer,
    ) {}

    public function sendCode(User $user, string $code): void
    {
        $email = new TemplatedEmail()
            ->to(new Address($user->getEmail() ?? ''))
            ->subject('Your DMARCo login code')
            ->htmlTemplate('emails/user/two-factor-code.html.twig')
            ->context([
                'code' => $code,
                'name' => $user->getName(),
            ])
        ;

        $this->mailer->send($email);
    }
}
