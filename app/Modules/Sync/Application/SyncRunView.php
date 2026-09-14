<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application;

use App\Modules\Sync\Domain\SyncError;
use App\Modules\Sync\Domain\SyncRun;
use App\Modules\Sync\Domain\SyncStats;
use App\Modules\Sync\Domain\SyncStatus;
use App\Modules\Sync\Domain\SyncTrigger;
use DateTimeImmutable;

/**
 * Прогон сбора в терминах приложения — то, что Application-слой отдаёт наружу.
 */
final readonly class SyncRunView
{
    public function __construct(
        public string $id,
        public string $organizationId,
        public SyncStatus $status,
        public SyncTrigger $trigger,
        public int $progressCurrent,
        public ?int $progressTotal,
        public int $attempts,
        public ?SyncError $error,
        public ?SyncStats $stats,
        public DateTimeImmutable $requestedAt,
        public ?DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $finishedAt,
    ) {}

    /**
     * Доменный прогон → представление для чтения.
     */
    public static function fromSyncRun(SyncRun $run): self
    {
        return new self(
            id: $run->id->value,
            organizationId: $run->organizationId,
            status: $run->status(),
            trigger: $run->trigger,
            progressCurrent: $run->progressCurrent(),
            progressTotal: $run->progressTotal(),
            attempts: $run->attempts(),
            error: $run->error(),
            stats: $run->stats(),
            requestedAt: $run->requestedAt,
            startedAt: $run->startedAt(),
            finishedAt: $run->finishedAt(),
        );
    }
}
