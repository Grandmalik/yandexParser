<?php

declare(strict_types=1);

namespace App\Modules\Sync\Interfaces\Http\AppConfig;

use App\Modules\Shared\Interfaces\Http\AppConfig\AppConfigSection;
use App\Modules\Shared\Interfaces\Http\Data\EnumOptionData;
use App\Modules\Sync\Domain\SyncStatus;
use App\Modules\Sync\Interfaces\Broadcasting\SyncRunUpdated;

/**
 * Секция `sync` ответа `/app-config`.
 */
final readonly class SyncConfigSection implements AppConfigSection
{
    public function key(): string
    {
        return 'sync';
    }

    public function data(): SyncConfigData
    {
        return new SyncConfigData(
            // Echo помечает пользовательские события точкой в начале имени, отличая их от событий фреймворка.
            progressEvent: '.'.SyncRunUpdated::NAME,
            pollingIntervalMs: config()->integer('sync.polling_interval_ms'),
            statuses: array_map(EnumOptionData::fromEnum(...), SyncStatus::cases()),
        );
    }
}
