<?php

declare(strict_types=1);

namespace App\DTO\Output\User;

final class TwoFactorApi
{
    public ?bool $twoFactorAppEnabled = null;
    public ?string $twoFactorAppOtpAuthUri = null;
    public ?string $twoFactorAppQrContent = null;
    public ?string $twoFactorAppSecret = null;
    public ?string $twoFactorMethod = null;
}
