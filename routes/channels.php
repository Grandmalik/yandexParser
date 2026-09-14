<?php

declare(strict_types=1);

use App\Modules\Organization\Application\Contracts\OrganizationChannel;
use App\Modules\Organization\Application\Contracts\OrganizationDirectory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;

/*
| Сюда уходит всё, что происходит с одной организацией: прогресс сбора, новые показатели.
| Доступ решает членство — то же правило, по которому работают REST-эндпоинты.
*/
Broadcast::channel(
    OrganizationChannel::PATTERN,
    static function (Authenticatable $user, string $organization): bool {
        $userId = $user->getAuthIdentifier();

        return is_numeric($userId)
            && app(OrganizationDirectory::class)->isMember($organization, (int) $userId);
    },
);
