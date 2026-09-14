<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application;

use App\Modules\Sync\Application\Contracts\Exceptions\SyncAlreadyRunning;
use App\Modules\Sync\Application\Port\SyncRunner;
use App\Modules\Sync\Domain\SyncRun;
use App\Modules\Sync\Domain\SyncRunId;
use App\Modules\Sync\Domain\SyncRunRepository;
use App\Modules\Sync\Domain\SyncTrigger;
use Illuminate\Database\ConnectionInterface;
use Psr\Clock\ClockInterface;

/**
 * Постановка сбора в очередь.
 */
final readonly class RequestSync
{
    public function __construct(
        private SyncRunRepository $runs,
        private SyncRunner $runner,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
    ) {}

    /**
     * Ставит сбор для организации. Активный прогон у организации может быть только один: автоматический запрос
     * присоединяется к уже идущему, а ручной получает отказ — чтобы пользователь следил за существующим прогоном,
     * а не плодил новые.
     *
     * @throws SyncAlreadyRunning
     */
    public function handle(string $organizationId, SyncTrigger $trigger): SyncRunView
    {
        $run = $this->connection->transaction(function () use ($organizationId, $trigger): SyncRun {
            $active = $this->runs->activeFor($organizationId);

            if ($active !== null) {
                if ($trigger === SyncTrigger::Manual) {
                    throw SyncAlreadyRunning::as($active->id->value);
                }

                return $active;
            }

            $run = SyncRun::queue(SyncRunId::generate(), $organizationId, $trigger, $this->clock->now());
            $this->runs->save($run);

            $this->runner->enqueue($run->id);

            return $run;
        });

        return SyncRunView::fromSyncRun($run);
    }
}
