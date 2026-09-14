<?php

declare(strict_types=1);

namespace App\Modules\Shared\Interfaces\Http\AppConfig;

use Spatie\LaravelData\Data;

/**
 * Вклад модуля в `GET /app-config`: параметры интерфейса и словари, которые SPA не имеет права зашивать у себя.
 * Модуль помечает свои секции тегом `AppConfigSection::TAG` в провайдере.
 */
interface AppConfigSection
{
    public const string TAG = 'app-config.sections';

    /**
     * Ключ секции верхнего уровня в ответе, например «reviews».
     */
    public function key(): string;

    /**
     * Содержимое секции.
     */
    public function data(): Data;
}
