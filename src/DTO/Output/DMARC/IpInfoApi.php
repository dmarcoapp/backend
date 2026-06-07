<?php

declare(strict_types=1);

namespace App\DTO\Output\DMARC;

final class IpInfoApi
{
    public ?string $orgName = null;

    public ?string $orgCountry = null;

    public ?string $orgAbuseEmail = null;

    public ?string $orgTechEmail = null;
}
