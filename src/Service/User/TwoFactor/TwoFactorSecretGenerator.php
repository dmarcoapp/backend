<?php

declare(strict_types=1);

namespace App\Service\User\TwoFactor;

use App\Helper\Base32Codec;

final readonly class TwoFactorSecretGenerator
{
    public function generate(): string
    {
        $random = random_bytes(20);

        return Base32Codec::encode($random);
    }
}
