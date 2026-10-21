<?php

declare(strict_types=1);

namespace App\Service\User\DMARC;

use App\DTO\Output\User\WeeklyOverview\WeeklyOverview;
use App\Entity\User\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class WeeklyOverviewMailer
{
    public function __construct(
        private MailerInterface $mailer,
    ) {}

    public function send(User $user, WeeklyOverview $overview): void
    {
        if (!$user->isWeeklyOverviewNotificationEnabled()) {
            return;
        }

        $emailAddress = $user->getEmail();
        if (null === $emailAddress || '' === trim($emailAddress)) {
            return;
        }

        $email = new TemplatedEmail()
            ->to(new Address($emailAddress))
            ->subject(self::subject($overview))
            ->htmlTemplate('emails/user/dmarc/weekly-overview.html.twig')
            ->textTemplate('emails/user/dmarc/weekly-overview.txt.twig')
            ->context([
                'overview' => $overview,
                'recipient' => $emailAddress,
            ])
        ;

        $this->mailer->send($email);
    }

    /**
     * Says what the week came down to, so the inbox list alone tells whether
     * the email needs opening.
     */
    public static function subject(WeeklyOverview $overview): string
    {
        $attention = $overview->domainsNeedingAttention();

        $outcome = match (true) {
            !$overview->hasReports() => 'no reports received',
            1 === \count($attention) => $attention[0]->domain.' needs attention',
            \count($attention) > 1 => \count($attention).' domains need attention',
            default => 'no issues found',
        };

        return \sprintf('DMARC weekly, %s: %s', $overview->periodLabel(), $outcome);
    }
}
