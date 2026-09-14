<?php

declare(strict_types=1);

namespace App\Modules\Sync\Interfaces\Console;

use App\Modules\Sync\Application\ScheduleResync;
use Illuminate\Console\Command;

/**
 * Плановый ресинк: ставит сбор организациям, чьи данные старше интервала обновления.
 */
final class ResyncDueOrganizationsCommand extends Command
{
    protected $signature = 'sync:resync-due';

    protected $description = 'Queues a fresh collection for organizations whose data is older than the re-sync interval.';

    /**
     * Один тик планировщика; печатает, скольким организациям сбор поставлен и кто пропущен.
     */
    public function handle(ScheduleResync $resync): int
    {
        $summary = $resync->handle();

        $this->components->info(sprintf(
            'Due: %d, queued: %d, already running: %d, platform paused: %d.',
            $summary->due,
            $summary->queued,
            $summary->alreadyRunning,
            $summary->platformPaused,
        ));

        return self::SUCCESS;
    }
}
