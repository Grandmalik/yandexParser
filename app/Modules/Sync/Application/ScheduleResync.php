<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application;

use App\Modules\Organization\Application\Contracts\ConnectedOrganization;
use App\Modules\Organization\Application\Contracts\OrganizationDirectory;
use App\Modules\Scraping\Application\Contracts\SourceAvailability;
use App\Modules\Sync\Domain\SyncRunRepository;
use App\Modules\Sync\Domain\SyncTrigger;
use DateInterval;
use Psr\Clock\ClockInterface;

/**
 * Не даёт сохранённым данным устаревать, но никогда не дёргает площадку всем списком сразу: за один тик берётся
 * ограниченная партия организаций, которые дольше всех не обновлялись.
 */
final readonly class ScheduleResync
{
    public function __construct(
        private OrganizationDirectory $organizations,
        private SyncRunRepository $runs,
        private SourceAvailability $sources,
        private RequestSync $requestSync,
        private ClockInterface $clock,
        private int $intervalMinutes,
        private int $batchSize,
    ) {}

    /**
     * Один тик планировщика: ставит сбор устаревшим организациям и отчитывается, кого пропустил и почему.
     */
    public function handle(): ResyncSummary
    {
        $due = $this->organizations->dueForResync(
            $this->clock->now()->sub(new DateInterval("PT{$this->intervalMinutes}M")),
            $this->batchSize,
        );

        $queued = 0;
        $running = 0;
        $paused = 0;

        foreach ($due as $organization) {
            match (true) {
                // Площадке, которая нас уже не пускает, ставить работу незачем: прогон всё равно упадёт.
                $this->sources->pausedFor($organization->source->platform) !== null => $paused++,
                $this->runs->activeFor($organization->id) !== null => $running++,
                default => $queued += $this->request($organization),
            };
        }

        return new ResyncSummary(count($due), $queued, $running, $paused);
    }

    /**
     * Ставит плановый сбор одной организации; возвращает 1 — так удобнее считать поставленные.
     */
    private function request(ConnectedOrganization $organization): int
    {
        $this->requestSync->handle($organization->id, SyncTrigger::Scheduled);

        return 1;
    }
}
