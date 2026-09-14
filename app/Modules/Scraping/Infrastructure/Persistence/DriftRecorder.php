<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Persistence;

use App\Modules\Scraping\Application\Contracts\Exceptions\SourceDrift;
use App\Modules\Shared\Application\Context\CorrelationContext;
use App\Modules\Shared\Domain\Source\SourceReference;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * Делает смену формата источника громкой: сохраняет сырой ответ и пишет в лог запись уровня critical.
 */
final readonly class DriftRecorder
{
    public function __construct(private ClockInterface $clock) {}

    /**
     * Фиксирует расхождение: запись в лог и образец ответа в БД для разбора.
     */
    public function record(SourceReference $source, SourceDrift $drift): void
    {
        $syncRunId = Context::get(CorrelationContext::SYNC_RUN_ID);

        Log::channel('scraping')->critical('Source format changed: the parser needs attention.', [
            'source' => $source->toString(),
            'stage' => $drift->stage->value,
            'expectation' => $drift->expectation,
            'http_status' => $drift->httpStatus,
        ]);

        try {
            SourcePayloadModel::query()->create([
                'sync_run_id' => is_string($syncRunId) ? $syncRunId : null,
                'platform' => $source->platform->value,
                'kind' => $drift->stage->value,
                'http_status' => $drift->httpStatus,
                'body' => $drift->payload,
                'created_at' => $this->clock->now(),
            ]);
        } catch (Throwable $exception) {
            // Потеря образца не должна скрыть сам факт того, что источник сменил формат.
            report($exception);
        }
    }
}
