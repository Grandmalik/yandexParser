<?php

declare(strict_types=1);

namespace App\Modules\Shared\Interfaces\Http\AppConfig;

/**
 * Всё, что браузеру нужно для websocket-соединения. Значения берутся из конфигурации сервера, поэтому SPA
 * не носит собственных параметров подключения, а при деплое они меняются в одном месте.
 */
final readonly class BroadcastingConfigSection implements AppConfigSection
{
    public function key(): string
    {
        return 'broadcasting';
    }

    public function data(): BroadcastingConfigData
    {
        // BROADCAST_CONNECTION=null приходит в конфигурацию настоящим null — а это и есть драйвер «null».
        $configured = config('broadcasting.default');
        $driver = is_string($configured) ? $configured : 'null';
        $connection = config()->array("broadcasting.connections.{$driver}", []);
        $options = is_array($connection['options'] ?? null) ? $connection['options'] : [];
        $key = is_string($connection['key'] ?? null) ? $connection['key'] : null;
        $port = $options['port'] ?? null;

        return new BroadcastingConfigData(
            // У драйверов без ключа (log, null) слушать некому: интерфейс переходит на опрос API.
            enabled: $key !== null && $key !== '',
            driver: $driver,
            key: $key,
            host: is_string($options['host'] ?? null) && $options['host'] !== '' ? $options['host'] : null,
            port: is_numeric($port) ? (int) $port : null,
            scheme: is_string($options['scheme'] ?? null) ? $options['scheme'] : null,
            // Путь, а не абсолютный URL: браузер достроит его до origin, на котором у него уже есть сессия.
            authEndpoint: '/broadcasting/auth',
        );
    }
}
