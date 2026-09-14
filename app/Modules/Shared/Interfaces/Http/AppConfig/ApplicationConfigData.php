<?php

declare(strict_types=1);

namespace App\Modules\Shared\Interfaces\Http\AppConfig;

use Spatie\LaravelData\Data;

/**
 * Имя приложения и локаль сервера: по ним фронтенд подписывает интерфейс и форматирует даты.
 */
final class ApplicationConfigData extends Data
{
    public function __construct(
        public string $name,
        public string $locale,
    ) {}
}
