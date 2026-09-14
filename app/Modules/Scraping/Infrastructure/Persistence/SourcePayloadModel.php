<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Facades\Date;

/**
 * Сырые ответы площадки; хранятся ровно столько, сколько нужно для разбора поломки парсера, остальное удаляет
 * штатная команда `model:prune`.
 *
 * @property int $id
 * @property string|null $sync_run_id
 * @property string $platform
 * @property string $kind
 * @property int|null $http_status
 * @property string $body
 * @property CarbonImmutable $created_at
 */
#[Table('source_payloads')]
#[Unguarded]
final class SourcePayloadModel extends Model
{
    use Prunable;

    public $timestamps = false;

    /**
     * Что считать устаревшим: ответы старше срока хранения из конфигурации.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where(
            'created_at',
            '<',
            Date::now()->subDays(config()->integer('scraping.payloads.retention_days')),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'http_status' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
