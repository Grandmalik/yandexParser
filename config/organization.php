<?php

declare(strict_types=1);

return [

    /*
    | Организаций на страницу списка. Клиент читает это число из `meta` ответа, а не зашивает у себя.
    */
    'per_page' => (int) env('ORGANIZATIONS_PER_PAGE', 50),

    /*
    | Сколько снимков показателей отдаёт история (по одному на сбор).
    */
    'history_limit' => (int) env('ORGANIZATION_HISTORY_LIMIT', 50),

    /*
    | Сколько подключений организаций разрешено пользователю за окно (каждое может обратиться к площадке).
    */
    'connect' => [
        'max_attempts' => (int) env('ORGANIZATION_CONNECT_MAX_ATTEMPTS', 10),
        'decay_seconds' => (int) env('ORGANIZATION_CONNECT_DECAY_SECONDS', 60),
    ],

];
