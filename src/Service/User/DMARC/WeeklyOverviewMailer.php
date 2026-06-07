<?php

declare(strict_types=1);

namespace App\Service\User\DMARC;

use App\DTO\Output\User\Dashboard;
use App\Entity\User\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class WeeklyOverviewMailer
{
    public function __construct(
        private MailerInterface $mailer,
    ) {}

    public function send(
        User $user,
        Dashboard $dashboard,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
    ): void {
        if (!$user->isWeeklyOverviewNotificationEnabled()) {
            return;
        }

        $emailAddress = $user->getEmail();
        if (null === $emailAddress || '' === trim($emailAddress)) {
            return;
        }

        $email = new TemplatedEmail()
            ->to(new Address($emailAddress))
            ->subject('Your weekly DMARC overview')
            ->htmlTemplate('emails/user/dmarc/weekly-overview.html.twig')
            ->context([
                'dashboard' => $dashboard,
                'name' => $user->getName(),
                'periodEnd' => $periodEnd,
                'periodStart' => $periodStart,
            ])
        ;

        $this->mailer->send($email);
    }
}
