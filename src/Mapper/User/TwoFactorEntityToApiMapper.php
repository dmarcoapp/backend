<?php

declare(strict_types=1);

namespace App\Mapper\User;

use App\DTO\Output\User\TwoFactorApi;
use App\Entity\User\User;
use App\Enum\User\TwoFactorMethod;
use App\Service\User\TwoFactor\TwoFactorQrCodeGenerator;
use App\Service\User\TwoFactor\TwoFactorService;
use Symfonycasts\MicroMapper\AsMapper;
use Symfonycasts\MicroMapper\MapperInterface;

#[AsMapper(from: User::class, to: TwoFactorApi::class)]
final readonly class TwoFactorEntityToApiMapper implements MapperInterface
{
    public function __construct(
        private TwoFactorQrCodeGenerator $twoFactorQrCodeGenerator,
        private TwoFactorService $twoFactorService,
    ) {}

    #[\Override]
    public function load(object $from, string $toClass, array $context): TwoFactorApi
    {
        return new TwoFactorApi();
    }

    #[\Override]
    public function populate(object $from, object $to, array $context): object
    {
        $user = $from;
        $dto = $to;

        assert($user instanceof User);
        assert($dto instanceof TwoFactorApi);

        $method = $this->twoFactorService->getMethod($user);

        $dto->twoFactorMethod = $method->value;
        $dto->twoFactorAppEnabled = TwoFactorMethod::APP === $method;

        if ($dto->twoFactorAppEnabled) {
            $dto->twoFactorAppOtpAuthUri = null;
            $dto->twoFactorAppQrContent = null;
            $dto->twoFactorAppSecret = null;

            return $dto;
        }

        $secret = $this->twoFactorService->ensureAppSecret($user);
        $otpAuthUri = $this->twoFactorService->getAppOtpAuthUri($user, $secret);
        $qrContent = $this->twoFactorQrCodeGenerator->generatePngBase64($otpAuthUri);

        $dto->twoFactorAppOtpAuthUri = $otpAuthUri;
        $dto->twoFactorAppQrContent = $qrContent;
        $dto->twoFactorAppSecret = $secret;

        return $dto;
    }
}
