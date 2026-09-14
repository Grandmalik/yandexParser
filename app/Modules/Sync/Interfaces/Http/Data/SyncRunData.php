<?php

declare(strict_types=1);

namespace App\Modules\Sync\Interfaces\Http\Data;

use App\Modules\Shared\Interfaces\Http\Data\EnumOptionData;
use App\Modules\Shared\Interfaces\Http\Errors\ErrorMessages;
use App\Modules\Sync\Application\SyncRunView;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * Прогон сбора в ответе API и в websocket-событии — одна и та же структура для обоих путей.
 */
final class SyncRunData extends Data
{
    public function __construct(
        public string $id,
        public string $organizationId,
        public EnumOptionData $status,
        /** Дошёл ли прогон до конечного статуса: клиент перестаёт следить по этому флагу, а не разбирая статус. */
        public bool $isFinished,
        public string $trigger,
        public SyncProgressData $progress,
        public int $attempts,
        public ?SyncFailureData $error,
        public ?SyncStatsData $stats,
        public CarbonImmutable $requestedAt,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $finishedAt,
    ) {}

    /**
     * Представление прогона → DTO ответа; сообщение об ошибке переводится здесь, на языке читателя.
     */
    public static function fromSyncRunView(SyncRunView $run, ErrorMessages $messages): self
    {
        return new self(
            id: $run->id,
            organizationId: $run->organizationId,
            status: EnumOptionData::fromEnum($run->status),
            isFinished: $run->status->isFinished(),
            trigger: $run->trigger->value,
            progress: SyncProgressData::of($run->progressCurrent, $run->progressTotal),
            attempts: $run->attempts,
            error: $run->error === null
                ? null
                : new SyncFailureData($run->error->code, $messages->translate($run->error->messageKey)),
            stats: $run->stats === null ? null : SyncStatsData::fromSyncStats($run->stats),
            requestedAt: CarbonImmutable::instance($run->requestedAt),
            startedAt: $run->startedAt === null ? null : CarbonImmutable::instance($run->startedAt),
            finishedAt: $run->finishedAt === null ? null : CarbonImmutable::instance($run->finishedAt),
        );
    }
}
