<?php

declare(strict_types=1);

namespace App\Service\Email;

use App\Entity\User\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class AggregateReportPostboxAddressProvider
{
    public function __construct(
        #[Autowire(env: 'APP_DMARC_AGGREGATE_REPORT_EMAIL_RECEIVER_DOMAIN')]
        private string $dmarcAggregateReportEmailReceiverDomain,
    ) {}

    public function getAddressForUser(User $user): string
    {
        return sprintf('%s@%s', $user->getSharedPostboxIdentifierToken(), $this->dmarcAggregateReportEmailReceiverDomain);
    }
}
