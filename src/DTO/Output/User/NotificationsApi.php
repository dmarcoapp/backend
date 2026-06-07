<?php

declare(strict_types=1);

namespace App\DTO\Output\User;

final class NotificationsApi
{
    public bool $unusualNewLoginNotificationEnabled = false;
    public bool $weeklyOverviewNotificationEnabled = false;
}
