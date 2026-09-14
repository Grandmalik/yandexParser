<?php

declare(strict_types=1);

return [

    /*
    | Очередь работы с площадкой: отдельно от основной, чтобы медленная площадка не задерживала другие задачи.
    */
    'queue' => env('SYNC_QUEUE', 'scraping'),

    'max_attempts' => (int) env('SYNC_MAX_ATTEMPTS', 5),
    'retry_until_minutes' => (int) env('SYNC_RETRY_UNTIL_MINUTES', 180),

    /*
    | Доля отзывов, которой может не хватать, чтобы сбор всё ещё считался полным: пока мы листаем страницы,
    | отзывы на площадке появляются и исчезают (ADR-001, D9).
    */
    'completeness_tolerance' => (float) env('SYNC_COMPLETENESS_TOLERANCE', 0.01),

    /*
    | Как часто SPA спрашивает прогресс незавершённого сбора, пока нет websocket (отдаётся через /app-config).
    */
    'polling_interval_ms' => (int) env('SYNC_POLLING_INTERVAL_MS', 2000),

    /*
    | Сбор дольше этого пишется в лог предупреждением: ранний признак того, что площадка режет скорость
    | или сбор уходит в повторы.
    */
    'slow_after_seconds' => (int) env('SYNC_SLOW_AFTER_SECONDS', 300),

    /*
    | Плановый пересбор: организация собирается заново, когда её данные старше интервала. За один запуск
    | берётся не больше batch_size, чтобы не бить по площадке всеми разом (частоту запусков задаёт планировщик).
    */
    'resync' => [
        'interval_minutes' => (int) env('SYNC_RESYNC_INTERVAL_MINUTES', 1440),
        'batch_size' => (int) env('SYNC_RESYNC_BATCH_SIZE', 5),
    ],

    /*
    | Сколько ручных запусков «Собрать заново» разрешено пользователю за окно.
    */
    'manual' => [
        'max_attempts' => (int) env('SYNC_MANUAL_MAX_ATTEMPTS', 10),
        'decay_seconds' => (int) env('SYNC_MANUAL_DECAY_SECONDS', 300),
    ],

];
