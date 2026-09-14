<?php

declare(strict_types=1);

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\FlatModuleWriter;

/**
 * Генерирует TypeScript-типы API для SPA из Data-классов и backed-энумов бэкенда (`composer types`).
 * Writer пишет всё в один плоский модуль, поэтому короткие имена Data-классов и энумов должны быть
 * уникальны по всем модулям.
 */
final class TypeScriptTransformerServiceProvider extends TypeScriptTransformerApplicationServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            ->extension(new LaravelDataTypeScriptTransformerExtension)
            ->transformer(new EnumTransformer)
            ->transformDirectories(app_path('Modules'))
            ->outputDirectory(resource_path('js/types/generated'))
            ->writer(new FlatModuleWriter('api.ts'));
    }
}
