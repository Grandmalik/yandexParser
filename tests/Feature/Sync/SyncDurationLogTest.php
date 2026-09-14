<?php

declare(strict_types=1);

use App\Modules\Sync\Application\Contracts\Events\SyncRunStateChanged;
use App\Modules\Sync\Application\SyncRunView;
use App\Modules\Sync\Domain\SyncStats;
use App\Modules\Sync\Domain\SyncStatus;
use App\Modules\Sync\Domain\SyncTrigger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Длительность выводится из меток времени прогона, но в базу никто не смотрит: завершившийся сбор обязан
 * сказать в структурированном логе, сколько он занял, и повысить голос, когда затянулся.
 */
function finishedRun(SyncStatus $status, int $seconds): SyncRunView
{
    $startedAt = new DateTimeImmutable('2026-09-12 10:00:00');

    return new SyncRunView(
        id: Str::uuid7()->toString(),
        organizationId: Str::uuid7()->toString(),
        status: $status,
        trigger: SyncTrigger::Manual,
        progressCurrent: 191,
        progressTotal: 191,
        attempts: 1,
        error: null,
        stats: new SyncStats(191, 0, 0, 0),
        requestedAt: $startedAt,
        startedAt: $startedAt,
        finishedAt: $startedAt->modify("+{$seconds} seconds"),
    );
}

it('records the duration of a finished collection', function (): void {
    config(['sync.slow_after_seconds' => 300]);
    Log::shouldReceive('channel')->with('scraping')->andReturnSelf();
    Log::shouldReceive('info')->once()->withArgs(
        fn (string $message, array $context): bool => $context['duration_seconds'] === 42.0
            && $context['status'] === 'completed'
            && $context['collected'] === 191,
    );

    event(new SyncRunStateChanged(finishedRun(SyncStatus::Completed, 42)));
});

it('warns when a collection dragged on far longer than expected', function (): void {
    config(['sync.slow_after_seconds' => 60]);
    Log::shouldReceive('channel')->with('scraping')->andReturnSelf();
    Log::shouldReceive('warning')->once()->withArgs(
        fn (string $message, array $context): bool => $context['duration_seconds'] === 900.0,
    );

    event(new SyncRunStateChanged(finishedRun(SyncStatus::Completed, 900)));
});

it('says nothing while a collection is still going', function (): void {
    Log::shouldReceive('channel')->never();

    $running = new SyncRunView(
        id: Str::uuid7()->toString(),
        organizationId: Str::uuid7()->toString(),
        status: SyncStatus::Running,
        trigger: SyncTrigger::Manual,
        progressCurrent: 50,
        progressTotal: 191,
        attempts: 1,
        error: null,
        stats: null,
        requestedAt: new DateTimeImmutable,
        startedAt: new DateTimeImmutable,
        finishedAt: null,
    );

    event(new SyncRunStateChanged($running));
});
