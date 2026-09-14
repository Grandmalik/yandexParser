<?php

declare(strict_types=1);

namespace App\Modules\Sync\Interfaces\Http\Data;

use App\Modules\Sync\Domain\SyncStats;
use Spatie\LaravelData\Data;

/**
 * Итоги сбора в ответе API: создано, изменено, без изменений, помечено удалёнными.
 */
final class SyncStatsData extends Data
{
    public function __construct(
        public int $created,
        public int $updated,
        public int $unchanged,
        public int $removed,
    ) {}

    /**
     * Доменная статистика → DTO ответа.
     */
    public static function fromSyncStats(SyncStats $stats): self
    {
        return new self($stats->created, $stats->updated, $stats->unchanged, $stats->removed);
    }
}
