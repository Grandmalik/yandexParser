<?php

declare(strict_types=1);

use App\Modules\Scraping\Application\Contracts\ScrapingErrorCode;
use App\Modules\Sync\Domain\InvalidSyncTransition;
use App\Modules\Sync\Domain\SyncRun;
use App\Modules\Sync\Domain\SyncRunId;
use App\Modules\Sync\Domain\SyncStats;
use App\Modules\Sync\Domain\SyncStatus;
use App\Modules\Sync\Domain\SyncTrigger;

function queuedRun(): SyncRun
{
    return SyncRun::queue(SyncRunId::generate(), 'organization-id', SyncTrigger::Manual, new DateTimeImmutable('2026-09-12 09:00:00'));
}

function stats(): SyncStats
{
    return new SyncStats(created: 10, updated: 2, unchanged: 5, removed: 1);
}

it('runs from queued to completed', function (): void {
    $run = queuedRun();
    $startedAt = new DateTimeImmutable('2026-09-12 10:00:00');
    $finishedAt = new DateTimeImmutable('2026-09-12 10:05:00');

    $run->start($startedAt);
    $run->advance(50, 600, 2);
    $run->complete(stats(), $finishedAt);

    expect($run->status())->toBe(SyncStatus::Completed)
        ->and($run->attempts())->toBe(1)
        ->and($run->progressCurrent())->toBe(50)
        ->and($run->progressTotal())->toBe(600)
        ->and($run->nextPage())->toBe(2)
        ->and($run->stats())->toEqual(stats())
        ->and($run->startedAt())->toBe($startedAt)
        ->and($run->finishedAt())->toBe($finishedAt)
        ->and($run->error())->toBeNull();
});

it('counts a restart of a crashed attempt and keeps the first start time', function (): void {
    $run = queuedRun();
    $firstStart = new DateTimeImmutable('2026-09-12 10:00:00');

    $run->start($firstStart);
    $run->start(new DateTimeImmutable('2026-09-12 10:01:00'));

    expect($run->attempts())->toBe(2)
        ->and($run->startedAt())->toBe($firstStart);
});

it('records why a collection is partial, with the key to translate it later', function (): void {
    $run = queuedRun();
    $run->start(new DateTimeImmutable);

    $run->completePartially(stats(), ScrapingErrorCode::Partial, new DateTimeImmutable);

    expect($run->status())->toBe(SyncStatus::Partial)
        ->and($run->error()?->code)->toBe('source.partial')
        ->and($run->error()?->messageKey)->toBe('scraping.errors.source.partial');
});

it('can fail before or during the collection', function (bool $started): void {
    $run = queuedRun();

    if ($started) {
        $run->start(new DateTimeImmutable);
    }

    $run->fail(ScrapingErrorCode::Unavailable, new DateTimeImmutable);

    expect($run->status())->toBe(SyncStatus::Failed)
        ->and($run->status()->isFinished())->toBeTrue()
        ->and($run->error()?->code)->toBe('source.unavailable');
})->with(['queued' => false, 'running' => true]);

it('refuses transitions out of a finished run', function (Closure $transition): void {
    $run = queuedRun();
    $run->start(new DateTimeImmutable);
    $run->complete(stats(), new DateTimeImmutable);

    $transition($run);
})->with([
    'start' => [fn (SyncRun $run) => $run->start(new DateTimeImmutable)],
    'advance' => [fn (SyncRun $run) => $run->advance(1, 1, 2)],
    'fail' => [fn (SyncRun $run) => $run->fail(ScrapingErrorCode::Unavailable, new DateTimeImmutable)],
    'complete' => [fn (SyncRun $run) => $run->complete(stats(), new DateTimeImmutable)],
])->throws(InvalidSyncTransition::class);

it('refuses to complete a run that never started', function (): void {
    queuedRun()->complete(stats(), new DateTimeImmutable);
})->throws(InvalidSyncTransition::class);

it('rejects progress beyond the total', function (): void {
    $run = queuedRun();
    $run->start(new DateTimeImmutable);

    $run->advance(601, 600, 2);
})->throws(InvalidArgumentException::class);

it('starts at the first page and remembers where to continue', function (): void {
    $run = queuedRun();
    expect($run->nextPage())->toBe(1);

    $run->start(new DateTimeImmutable);
    $run->advance(100, 600, 3);

    expect($run->nextPage())->toBe(3);
});

it('rejects a page number below the first one', function (): void {
    $run = queuedRun();
    $run->start(new DateTimeImmutable);

    $run->advance(50, 600, 0);
})->throws(InvalidArgumentException::class);
