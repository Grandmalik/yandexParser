<?php

declare(strict_types=1);

namespace App\Modules\Sync\Infrastructure\Queue;

use App\Modules\Sync\Application\Port\SyncRunner;
use App\Modules\Sync\Domain\SyncRunId;
use App\Modules\Sync\Infrastructure\Jobs\SyncOrganizationJob;

/**
 * Ставит прогон в очередь Laravel — на отдельную очередь сбора из конфигурации.
 */
final readonly class QueueSyncRunner implements SyncRunner
{
    public function enqueue(SyncRunId $id): void
    {
        // Только после коммита: воркер не должен подхватить прогон, который транзакция ещё может откатить.
        SyncOrganizationJob::dispatch($id->value)
            ->onQueue(config()->string('sync.queue'))
            ->afterCommit();
    }
}
