<?php

declare(strict_types=1);

namespace App\Service\Email;

use App\Entity\User\User;
use App\Repository\User\UserRepository;
use Symfony\Component\String\ByteString;

final readonly class AggregateReportPostboxResolver
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    /**
     * The inverse of AggregateReportPostboxAddressProvider: the local part of a
     * postbox address is the owner's shared postbox identifier token.
     */
    public function resolveUser(string $postboxAddress): ?User
    {
        $address = trim($postboxAddress);

        if (!str_contains($address, '@')) {
            return null;
        }

        $identifier = new ByteString($address)->before('@')->toString();

        if ('' === $identifier) {
            return null;
        }

        return $this->userRepository->findOneBy(['sharedPostboxIdentifierToken' => $identifier]);
    }
}
