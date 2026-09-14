<?php

declare(strict_types=1);

namespace App\Modules\Sync\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Запись агрегата SyncRun в БД; пишется только через EloquentSyncRunRepository.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $trigger
 * @property string $status
 * @property int $progress_current
 * @property int|null $progress_total
 * @property int $next_page
 * @property int $attempts
 * @property string|null $error_code
 * @property string|null $error_message_key
 * @property array<string, int>|null $stats
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable $created_at
 */
#[Table('sync_runs', keyType: 'string', incrementing: false)]
#[Unguarded]
final class SyncRunModel extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'progress_current' => 'integer',
            'progress_total' => 'integer',
            'next_page' => 'integer',
            'attempts' => 'integer',
            'stats' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
