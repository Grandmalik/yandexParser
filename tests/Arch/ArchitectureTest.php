<?php

declare(strict_types=1);

/*
| Границы слоёв и модулей, см. ai/guidelines.md §1.4.
| Модули находятся сканированием файловой системы, поэтому новый bounded context покрывается автоматически.
*/

$modules = array_map(basename(...), glob(dirname(__DIR__, 2).'/app/Modules/*', GLOB_ONLYDIR) ?: []);

/**
 * Пространства имён указанных слоёв во всех модулях, кроме `$module` и общего ядра.
 *
 * @param  list<string>  $modules
 * @param  list<string>  $layers
 * @return list<string>
 */
$foreignLayers = static function (array $modules, string $module, array $layers): array {
    $namespaces = [];

    foreach (array_diff($modules, [$module, 'Shared']) as $other) {
        foreach ($layers as $layer) {
            $namespaces[] = "App\Modules\\{$other}\\{$layer}";
        }
    }

    return $namespaces;
};

arch('php preset')->preset()->php();

arch('security preset')->preset()->security();

arch('modules declare strict types')
    ->expect('App\Modules')
    ->toUseStrictTypes();

arch('domain events are delivered only after the transaction commits')
    ->expect([
        'App\Modules\Organization\Application\Contracts\Events',
        'App\Modules\Sync\Application\Contracts\Events',
    ])
    ->toImplement('Illuminate\Contracts\Events\ShouldDispatchAfterCommit');

arch('shared kernel depends on no other module')
    ->expect('App\Modules\Shared')
    ->not->toUse(array_map(static fn (string $module): string => "App\Modules\\{$module}", array_values(array_diff($modules, ['Shared']))));

foreach ($modules as $module) {
    $namespace = "App\Modules\\{$module}";

    arch("{$module}: domain is framework-agnostic and isolated")
        ->expect("{$namespace}\\Domain")
        ->not->toUse([
            'Illuminate',
            'Laravel',
            "{$namespace}\\Application",
            "{$namespace}\\Infrastructure",
            "{$namespace}\\Interfaces",
            'App\Modules\Shared\Application',
            'App\Modules\Shared\Infrastructure',
            ...$foreignLayers($modules, $module, ['Domain', 'Application', 'Infrastructure', 'Interfaces']),
        ]);

    arch("{$module}: application knows nothing about delivery and persistence")
        ->expect("{$namespace}\\Application")
        ->not->toUse([
            'Illuminate\Http',
            // Транзакция подключения допустима; модели, построитель запросов и схема — нет.
            'Illuminate\Database\Eloquent',
            'Illuminate\Database\Query',
            'Illuminate\Database\Schema',
            'Illuminate\Queue',
            'Illuminate\Support\Facades',
            "{$namespace}\\Infrastructure",
            "{$namespace}\\Interfaces",
            ...$foreignLayers($modules, $module, ['Domain', 'Infrastructure', 'Interfaces']),
        ]);

    arch("{$module}: interfaces talk to the application layer only")
        ->expect("{$namespace}\\Interfaces")
        ->not->toUse([
            "{$namespace}\\Infrastructure",
            ...$foreignLayers($modules, $module, ['Domain', 'Infrastructure', 'Interfaces']),
        ]);

    arch("{$module}: infrastructure does not reach into other modules' internals")
        ->expect("{$namespace}\\Infrastructure")
        ->not->toUse($foreignLayers($modules, $module, ['Domain', 'Infrastructure', 'Interfaces']));
}
