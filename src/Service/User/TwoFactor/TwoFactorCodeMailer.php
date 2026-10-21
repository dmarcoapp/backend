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
            ->subject('Your DMARCo sign-in code')
            ->htmlTemplate('emails/user/two-factor-code.html.twig')
            ->textTemplate('emails/user/two-factor-code.txt.twig')
            ->context([
                'code' => $code,
                'name' => $user->getName(),
            ])
        ;

        $this->mailer->send($email);
    }
}
