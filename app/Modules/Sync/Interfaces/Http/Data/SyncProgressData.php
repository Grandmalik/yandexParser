<?php

declare(strict_types=1);

namespace App\Modules\Sync\Interfaces\Http\Data;

use Spatie\LaravelData\Data;

/**
 * Прогресс сбора: сколько собрано, сколько ожидается, и какой это процент.
 */
final class SyncProgressData extends Data
{
    /**
     * @param  int|null  $total  Неизвестно, пока площадка не сообщит, сколько у неё отзывов.
     * @param  int|null  $percent  Считается здесь, чтобы клиенту не пришлось считать самому.
     */
    public function __construct(
        public int $current,
        public ?int $total,
        public ?int $percent,
    ) {}

    /**
     * Собирает прогресс и сразу считает процент; при неизвестном или нулевом общем числе процента нет.
     */
    public static function of(int $current, ?int $total): self
    {
        return new self(
            current: $current,
            total: $total,
            percent: $total === null || $total === 0 ? null : min(100, (int) floor($current / $total * 100)),
        );
    }
}
