<?php

declare(strict_types=1);

namespace App\DTO\Output\Intake;

final class MailboxApi
{
    public string $id = '';
    public string $name = '';
    public string $host = '';
    public int $port = 0;
    public string $security = '';
    public string $username = '';
    public string $authMethod = '';

    /**
     * True when the stored password was encrypted with another key.
     */
    public bool $passwordUnreadable = false;
    public string $folder = '';
    public ?string $reportAddress = null;
    public string $afterImport = '';
    public ?string $moveToFolder = null;
    public bool $enabled = true;
    public int $pollIntervalMinutes = 0;
    public int $firstImportDays = 0;
    public ?\DateTimeImmutable $lastPolledAt = null;
    public ?\DateTimeImmutable $lastSuccessAt = null;
    public ?string $lastError = null;
    public int $consecutiveFailures = 0;
    public ?\DateTimeImmutable $createdAt = null;
}
