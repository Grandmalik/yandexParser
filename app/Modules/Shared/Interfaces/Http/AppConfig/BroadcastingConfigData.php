<?php

declare(strict_types=1);

namespace App\Modules\Shared\Interfaces\Http\AppConfig;

use Spatie\LaravelData\Data;

/**
 * Параметры websocket-подключения для браузера.
 */
final class BroadcastingConfigData extends Data
{
    /**
     * @param  bool  $enabled  false, если websocket-сервер не настроен; тогда интерфейс опрашивает API.
     * @param  string  $authEndpoint  Куда браузер обращается, чтобы подтвердить право слушать приватный канал.
     */
    public function __construct(
        public bool $enabled,
        public string $driver,
        public ?string $key,
        public ?string $host,
        public ?int $port,
        public ?string $scheme,
        public string $authEndpoint,
    ) {}
}
