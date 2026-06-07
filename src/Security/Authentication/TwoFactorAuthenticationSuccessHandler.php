<?php

declare(strict_types=1);

namespace App\Security\Authentication;

use App\Entity\User\User;
use App\Enum\User\TwoFactorMethod;
use App\Service\User\TwoFactor\TwoFactorCodeMailer;
use App\Service\User\TwoFactor\TwoFactorService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final readonly class TwoFactorAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        #[Autowire(service: 'lexik_jwt_authentication.handler.authentication_success')]
        private AuthenticationSuccessHandlerInterface $inner,
        private TwoFactorService $twoFactorService,
        private TwoFactorCodeMailer $twoFactorCodeMailer,
    ) {}

    #[\Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return $this->inner->onAuthenticationSuccess($request, $token);
        }

        $code = $this->extractCode($request);
        if (null === $code) {
            $this->sendEmailCodeIfNeeded($user);

            return new JsonResponse(
                [
                    'code' => Response::HTTP_UNAUTHORIZED,
                    'message' => 'Two-factor code required.',
                    'error' => 'two_factor_required',
                ],
                Response::HTTP_UNAUTHORIZED
            );
        }

        if (!$this->twoFactorService->isValidCode($user, $code)) {
            return new JsonResponse(
                [
                    'code' => Response::HTTP_UNAUTHORIZED,
                    'message' => 'Invalid two-factor code.',
                    'error' => 'two_factor_invalid',
                ],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return $this->inner->onAuthenticationSuccess($request, $token);
    }

    private function sendEmailCodeIfNeeded(User $user): void
    {
        if (TwoFactorMethod::EMAIL !== $this->twoFactorService->getMethod($user)) {
            return;
        }

        $code = $this->twoFactorService->getEmailCode($user);
        $this->twoFactorCodeMailer->sendCode($user, $code);
    }

    private function extractCode(Request $request): ?string
    {
        $content = $request->getContent();
        if ('' === $content) {
            return null;
        }

        try {
            $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($payload)) {
            return null;
        }

        $keys = ['two_factor_code', 'twoFactorCode'];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }
            $value = $payload[$key];
            if (is_int($value)) {
                return (string) $value;
            }
            if (is_string($value)) {
                $trimmed = trim($value);

                return '' === $trimmed ? null : $trimmed;
            }
        }

        return null;
    }
}
