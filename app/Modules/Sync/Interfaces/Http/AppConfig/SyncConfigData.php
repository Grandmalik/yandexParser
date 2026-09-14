<?php

declare(strict_types=1);

namespace App\Modules\Sync\Interfaces\Http\AppConfig;

use App\Modules\Shared\Interfaces\Http\Data\EnumOptionData;
use Spatie\LaravelData\Data;

/**
 * Секция `sync` ответа `/app-config`: всё о сборе, что фронтенд не имеет права зашивать у себя.
 */
final class SyncConfigData extends Data
{
    /**
     * @param  string  $progressEvent  Имя события с прогрессом; клиент никогда не составляет его сам.
     * @param  int  $pollingIntervalMs  Как часто спрашивать прогресс, когда websocket недоступен.
     * @param  list<EnumOptionData>  $statuses  Все статусы с подписями, чтобы клиент не сопоставлял значения текстам.
     */
    public function __construct(
        public string $progressEvent,
        public int $pollingIntervalMs,
        public array $statuses,
    ) {}
}
