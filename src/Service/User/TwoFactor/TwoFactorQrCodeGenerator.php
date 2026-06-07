<?php

declare(strict_types=1);

namespace App\Service\User\TwoFactor;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

final readonly class TwoFactorQrCodeGenerator
{
    public function generatePngBase64(string $data): string
    {
        $renderer = new GDLibRenderer(
            size: 160,
            margin: 1,
            imageFormat: 'png',
            compressionQuality: 9,
        );
        $writer = new Writer($renderer);

        $result = $writer->writeString($data);

        return base64_encode($result);
    }
}
