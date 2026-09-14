<?php

declare(strict_types=1);

namespace App\Modules\Sync\Infrastructure\Broadcasting;

use App\Modules\Shared\Interfaces\Http\Errors\ErrorMessages;
use App\Modules\Sync\Application\Contracts\Events\SyncRunStateChanged;
use App\Modules\Sync\Interfaces\Broadcasting\SyncRunUpdated;
use App\Modules\Sync\Interfaces\Http\Data\SyncRunData;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Превращает доменное событие в то единственное, на что подписан браузер. Перевод живёт здесь, поэтому
 * Application-слой не знает, что за ним кто-то наблюдает.
 */
final readonly class BroadcastSyncRunState
{
    public function __construct(
        private Dispatcher $events,
        private ErrorMessages $messages,
    ) {}

    /**
     * Рассылает состояние прогона в канал его организации.
     */
    public function handle(SyncRunStateChanged $event): void
    {
        $this->events->dispatch(new SyncRunUpdated(SyncRunData::fromSyncRunView($event->run, $this->messages)));
    }
}
