<?php

declare(strict_types=1);

namespace App\Service\User\TwoFactor;

use App\Entity\User\User;
use App\Enum\User\TwoFactorMethod;

final readonly class TwoFactorService
{
    private const int APP_PERIOD_SECONDS = 30;
    private const int CODE_DIGITS = 6;
    private const int CODE_WINDOW = 1;
    private const string ISSUER = 'DMARCo';

    public function __construct(
        private TwoFactorSecretManager $secretManager,
        private TwoFactorTotpService $totpService,
    ) {}

    public function getMethod(User $user): ?TwoFactorMethod
    {
        return $user->getTwoFactorMethod();
    }

    public function isEnabled(User $user): bool
    {
        return null !== $this->getMethod($user);
    }

    public function ensureAppSecret(User $user): string
    {
        return $this->secretManager->ensureAppSecret($user);
    }

    public function getAppCode(User $user): string
    {
        $secret = $this->ensureAppSecret($user);

        return $this->totpService->generateCode($secret, self::APP_PERIOD_SECONDS, self::CODE_DIGITS);
    }

    public function isValidCode(User $user, string $code): bool
    {
        if (TwoFactorMethod::APP !== $this->getMethod($user)) {
            return false;
        }

        $secret = $user->getTwoFactorAppSecret();
        if (null === $secret) {
            return false;
        }

        return $this->totpService->verifyCode($secret, $code, self::APP_PERIOD_SECONDS, self::CODE_DIGITS, self::CODE_WINDOW);
    }

    public function isValidAppCode(User $user, string $code): bool
    {
        $secret = $this->ensureAppSecret($user);

        return $this->totpService->verifyCode($secret, $code, self::APP_PERIOD_SECONDS, self::CODE_DIGITS, self::CODE_WINDOW);
    }

    public function getAppOtpAuthUri(User $user, string $secret): string
    {
        $label = rawurlencode(self::ISSUER.':'.($user->getEmail() ?? ''));
        $issuer = rawurlencode(self::ISSUER);

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&digits=%d&period=%d',
            $label,
            $secret,
            $issuer,
            self::CODE_DIGITS,
            self::APP_PERIOD_SECONDS,
        );
    }
}
