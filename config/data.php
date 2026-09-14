<?php

declare(strict_types=1);

use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/*
| Только отличия от конфигурации spatie/laravel-data по умолчанию: пакет сливает её с этим файлом
| по ключам верхнего уровня.
*/
return [

    /*
    | Ответы оборачиваются в `{"data": ...}`.
    */
    'wrap' => 'data',

    /*
    | Data-классы лежат в модулях; кэш их структуры собирается оттуда.
    */
    'structure_caching' => [
        'enabled' => true,
        'directories' => [app_path('Modules')],
        'cache' => [
            'store' => env('CACHE_STORE', 'database'),
            'prefix' => 'laravel-data',
            'duration' => null,
        ],
        'reflection_discovery' => [
            'enabled' => true,
            'base_path' => base_path(),
            'root_namespace' => null,
        ],
    ],

    /*
    | Контракт API — snake_case (ai/guidelines.md §3.3), сгенерированные TS-типы следуют ему.
    */
    'name_mapping_strategy' => [
        'input' => null,
        'output' => SnakeCaseMapper::class,
    ],

];
