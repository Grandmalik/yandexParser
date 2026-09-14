<?php

declare(strict_types=1);

namespace App\Modules\Sync\Infrastructure\Listeners;

use App\Modules\Sync\Application\Contracts\Events\SyncRunStateChanged;
use App\Modules\Sync\Application\SyncRunView;
use Illuminate\Support\Facades\Log;

/**
 * Сколько сбор занял на самом деле. Длительность выводится из меток времени прогона, но в базу никто не смотрит:
 * эта запись кладёт её в структурированный лог рядом с сигналами площадки и отдельно сообщает, когда прогон
 * затянулся сильно дольше ожидаемого — ранний симптом троттлинга или замедления площадки.
 */
final readonly class LogSyncDuration
{
    public function __construct(private int $slowAfterSeconds) {}

    /**
     * Пишет запись в лог, когда прогон дошёл до конечного статуса.
     */
    public function handle(SyncRunStateChanged $event): void
    {
        $run = $event->run;

        if (! $run->status->isFinished()) {
            return;
        }

        $seconds = $this->seconds($run);
        $context = [
            'sync_run_id' => $run->id,
            'organization_id' => $run->organizationId,
            'status' => $run->status->value,
            'trigger' => $run->trigger->value,
            'attempts' => $run->attempts,
            'collected' => $run->progressCurrent,
            'expected' => $run->progressTotal,
            'duration_seconds' => $seconds,
            'error' => $run->error?->code,
        ];

        $seconds !== null && $seconds >= $this->slowAfterSeconds
            ? Log::channel('scraping')->warning('Collection took much longer than expected.', $context)
            : Log::channel('scraping')->info('Collection finished.', $context);
    }

    /**
     * Длительность прогона в секундах; null — если он не начинался или не завершился.
     */
    private function seconds(SyncRunView $run): ?float
    {
        if ($run->startedAt === null || $run->finishedAt === null) {
            return null;
        }

        return round((float) $run->finishedAt->format('U.u') - (float) $run->startedAt->format('U.u'), 1);
    }
}
