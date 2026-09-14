<?php

declare(strict_types=1);

return [

    /*
    | Guard сессии, в который входит SPA. Задан явно: на защищённых маршрутах `auth:sanctum` переключает guard
    | по умолчанию на `sanctum`, а вход и выход должны работать с сессией.
    */
    'guard' => 'web',

    /*
    | Сколько попыток входа разрешено на пару «email + IP» за окно.
    */
    'login' => [
        'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'decay_seconds' => (int) env('LOGIN_DECAY_SECONDS', 60),
    ],

];
