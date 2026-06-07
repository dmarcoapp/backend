<?php

declare(strict_types=1);

namespace App\DTO\Output\DMARC;

use App\Enum\DMARC\Domain\ProtectionLevel;
use Symfony\Component\Uid\Uuid;

final class DomainApi
{
    public Uuid $id;
    public ?string $domain;
    public ?\DateTimeInterface $lastChecked;
    public ?string $dmarcRecord;
    public ?bool $isConfiguredCorrectly;
    public ?ProtectionLevel $protectionLevel;
    public ?\DateTimeInterface $createdAt;

    public function __construct(Uuid $id)
    {
        $this->id = $id;
    }
}
