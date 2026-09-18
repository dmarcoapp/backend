<?php

declare(strict_types=1);

namespace App\Service\User\TwoFactor;

use App\Entity\User\User;
use App\Enum\User\TwoFactorMethod;

final readonly class TwoFactorService
{
    private const int EMAIL_PERIOD_SECONDS = 300;
    private const int APP_PERIOD_SECONDS = 30;
    private const int CODE_DIGITS = 6;
    private const int CODE_WINDOW = 1;
    private const string ISSUER = 'DMARCo';

    public function __construct(
        private TwoFactorSecretManager $secretManager,
        private TwoFactorTotpService $totpService,
    ) {}

    public function getMethod(User $user): TwoFactorMethod
    {
        return $user->getTwoFactorMethod();
    }

    public function ensureEmailSecret(User $user): string
    {
        return $this->secretManager->ensureEmailSecret($user);
    }

    public function ensureAppSecret(User $user): string
    {
        return $this->secretManager->ensureAppSecret($user);
    }

    public function getEmailCode(User $user): string
    {
        $secret = $this->ensureEmailSecret($user);

        return $this->totpService->generateCode($secret, self::EMAIL_PERIOD_SECONDS, self::CODE_DIGITS);
    }

    public function getAppCode(User $user): string
    {
        $secret = $this->ensureAppSecret($user);

        return $this->totpService->generateCode($secret, self::APP_PERIOD_SECONDS, self::CODE_DIGITS);
    }

    public function isValidCode(User $user, string $code): bool
    {
        $method = $this->getMethod($user);
        if (TwoFactorMethod::APP === $method) {
            $secret = $user->getTwoFactorAppSecret();
            if (null === $secret) {
                return false;
            }

            return $this->totpService->verifyCode($secret, $code, self::APP_PERIOD_SECONDS, self::CODE_DIGITS, self::CODE_WINDOW);
        }

        return $this->consumeEmailCode($user, $code);
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

    /**
     * A mailed code stays valid for the whole acceptance window, long enough
     * that anyone who reads the message could sign in with it again. Accepting
     * one therefore rotates the secret it came from, which retires that code
     * and every other one already in the owner's mailbox. Rotating beats
     * recording the spent time step, because a code derived from an unchanged
     * secret is the same code for the rest of the step: the owner signing in
     * again within those five minutes would be mailed what they just used.
     */
    private function consumeEmailCode(User $user, string $code): bool
    {
        $isValid = $this->totpService->verifyCode(
            $this->ensureEmailSecret($user),
            $code,
            self::EMAIL_PERIOD_SECONDS,
            self::CODE_DIGITS,
            self::CODE_WINDOW,
        );

        if (!$isValid) {
            return false;
        }

        $this->secretManager->rotateEmailSecret($user);

        return true;
    }
}
