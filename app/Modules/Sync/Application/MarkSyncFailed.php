<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application;

use App\Modules\Sync\Application\Contracts\Events\SyncFailed;
use App\Modules\Sync\Application\Contracts\Events\SyncRunStateChanged;
use App\Modules\Sync\Domain\SyncRunId;
use App\Modules\Sync\Domain\SyncRunRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * Переводит прогон в конечное состояние, когда повторять бессмысленно или попытки кончились: прогон не должен
 * навсегда зависать в статусе «идёт сбор».
 */
final readonly class MarkSyncFailed
{
    public function __construct(
        private SyncRunRepository $runs,
        private Dispatcher $events,
        private ClockInterface $clock,
    ) {}

    /**
     * Помечает прогон упавшим и сообщает об этом; уже завершённый прогон не трогает.
     */
    public function handle(SyncRunId $id, Throwable $failure): void
    {
        $run = $this->runs->find($id);

        if ($run === null || $run->status()->isFinished()) {
            return;
        }

        $error = SyncFailure::codeOf($failure);
        $run->fail($error, $this->clock->now());
        $this->runs->save($run);

        $this->events->dispatch(new SyncRunStateChanged(SyncRunView::fromSyncRun($run)));
        $this->events->dispatch(new SyncFailed($run->id->value, $run->organizationId, $error->code()));
    }
}
