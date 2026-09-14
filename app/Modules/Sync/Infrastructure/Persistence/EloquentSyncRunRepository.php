<?php

declare(strict_types=1);

namespace App\Modules\Sync\Infrastructure\Persistence;

use App\Modules\Sync\Domain\SyncError;
use App\Modules\Sync\Domain\SyncRun;
use App\Modules\Sync\Domain\SyncRunId;
use App\Modules\Sync\Domain\SyncRunRepository;
use App\Modules\Sync\Domain\SyncStats;
use App\Modules\Sync\Domain\SyncStatus;
use App\Modules\Sync\Domain\SyncTrigger;

/**
 * Хранение прогонов сбора в базе данных.
 */
final readonly class EloquentSyncRunRepository implements SyncRunRepository
{
    public function find(SyncRunId $id): ?SyncRun
    {
        $record = SyncRunModel::query()->find($id->value);

        return $record === null ? null : $this->toDomain($record);
    }

    public function activeFor(string $organizationId): ?SyncRun
    {
        $record = SyncRunModel::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', [SyncStatus::Queued->value, SyncStatus::Running->value])
            ->latest('created_at')
            ->first();

        return $record === null ? null : $this->toDomain($record);
    }

    public function latestFor(string $organizationId): ?SyncRun
    {
        $record = SyncRunModel::query()
            ->where('organization_id', $organizationId)
            ->latest('created_at')
            ->latest('id')
            ->first();

        return $record === null ? null : $this->toDomain($record);
    }

    public function save(SyncRun $run): void
    {
        $stats = $run->stats();
        $error = $run->error();

        SyncRunModel::query()->updateOrCreate(['id' => $run->id->value], [
            'organization_id' => $run->organizationId,
            'trigger' => $run->trigger->value,
            'status' => $run->status()->value,
            'progress_current' => $run->progressCurrent(),
            'progress_total' => $run->progressTotal(),
            'next_page' => $run->nextPage(),
            'attempts' => $run->attempts(),
            'error_code' => $error?->code,
            'error_message_key' => $error?->messageKey,
            'stats' => $stats === null ? null : [
                'created' => $stats->created,
                'updated' => $stats->updated,
                'unchanged' => $stats->unchanged,
                'removed' => $stats->removed,
            ],
            'started_at' => $run->startedAt(),
            'finished_at' => $run->finishedAt(),
            'created_at' => $run->requestedAt,
        ]);
    }

    /**
     * Строка таблицы `sync_runs` → доменный прогон.
     */
    private function toDomain(SyncRunModel $record): SyncRun
    {
        $stats = $record->stats;

        return SyncRun::restore(
            id: SyncRunId::fromString($record->id),
            organizationId: $record->organization_id,
            trigger: SyncTrigger::from($record->trigger),
            requestedAt: $record->created_at,
            status: SyncStatus::from($record->status),
            progressCurrent: $record->progress_current,
            progressTotal: $record->progress_total,
            nextPage: $record->next_page,
            attempts: $record->attempts,
            error: $record->error_code === null || $record->error_message_key === null
                ? null
                : new SyncError($record->error_code, $record->error_message_key),
            stats: $stats === null ? null : new SyncStats(
                created: (int) ($stats['created'] ?? 0),
                updated: (int) ($stats['updated'] ?? 0),
                unchanged: (int) ($stats['unchanged'] ?? 0),
                removed: (int) ($stats['removed'] ?? 0),
            ),
            startedAt: $record->started_at,
            finishedAt: $record->finished_at,
        );
    }
}
